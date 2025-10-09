import BottomPanelView from 'views/record/panels/bottom';

export default class extends BottomPanelView {
    template = 'record/panels/side'

    setupFields() {
        this.fieldList = [];
    }

    async setup() {
        super.setup();

        this.once('after:render', async () => {
            const el = this.$el.find('.panel-body');

            // Controls container
            const controls = document.createElement('div');
            controls.className = 'flex-row mb-2 gap-2';
            controls.innerHTML = `
                <div class="form-inline">
                  <select data-name="typeFilter" class="form-control input-sm" style="min-width: 160px;">
                    <option value="">Type: All</option>
                    <option>BusinessService</option>
                    <option>Application</option>
                    <option>Database</option>
                    <option>Server</option>
                    <option>Network</option>
                    <option>Storage</option>
                    <option>Other</option>
                  </select>
                  <select data-name="statusFilter" class="form-control input-sm" style="min-width: 140px;">
                    <option value="">Status: All</option>
                    <option>Active</option>
                    <option>Planned</option>
                    <option>Retired</option>
                    <option>Error</option>
                  </select>
                  <select data-name="envFilter" class="form-control input-sm" style="min-width: 120px;">
                    <option value="">Env: All</option>
                    <option>Prod</option>
                    <option>PreProd</option>
                    <option>QA</option>
                    <option>Dev</option>
                    <option>Lab</option>
                  </select>
                  <button data-action="exportPng" class="btn btn-default btn-sm"><i class="fas fa-download"></i> Export PNG</button>
                </div>
                <div class="pull-right" style="display:flex;gap:8px;align-items:center;">
                  <span style="display:flex;gap:8px;align-items:center;">
                    <span style="width:10px;height:10px;background:#5cb85c;display:inline-block"></span> BS
                    <span style="width:10px;height:10px;background:#337ab7;display:inline-block"></span> App
                    <span style="width:10px;height:10px;background:#8a6d3b;display:inline-block"></span> DB
                    <span style="width:10px;height:10px;background:#d9534f;display:inline-block"></span> Server
                    <span style="width:10px;height:10px;background:#5bc0de;display:inline-block"></span> Net
                    <span style="width:10px;height:10px;background:#f0ad4e;display:inline-block"></span> Storage
                  </span>
                </div>
            `;
            el.append(controls);

            const accountId = this.model.id;

            let cis = await this._fetchCis(accountId);
            let {nodes, edges} = this._buildGraph(cis);

            const graphEl = document.createElement('div');
            el.append(graphEl);

            const render = () => this._renderGraph($(graphEl), nodes, edges);
            render();

            const onFilter = () => {
                const type = controls.querySelector('[data-name="typeFilter"]').value || null;
                const status = controls.querySelector('[data-name="statusFilter"]').value || null;
                const env = controls.querySelector('[data-name="envFilter"]').value || null;

                const filtered = cis.filter(ci => (
                    (!type || ci.type === type) &&
                    (!status || ci.status === status) &&
                    (!env || ci.environment === env)
                ));

                const built = this._buildGraph(filtered);
                nodes = built.nodes;
                edges = built.edges;
                render();
            };

            controls.querySelector('[data-name="typeFilter"]').addEventListener('change', onFilter);
            controls.querySelector('[data-name="statusFilter"]').addEventListener('change', onFilter);
            controls.querySelector('[data-name="envFilter"]').addEventListener('change', onFilter);

            this.addActionHandler('exportPng', () => this._exportGraphImage(graphEl));
        });
    }

    async _fetchCis(accountId) {
        // Fetch JSLCi records for the account
        const collection = await this.getCollectionFactory().create('JSLCi');

        collection.where = [{type: 'equals', attribute: 'accountId', value: accountId}];

        await collection.fetch({maxSize: 500});

        return collection.models.map(m => m.attributes);
    }

    _buildGraph(cis) {
        const nodes = [];
        const edges = [];

        const idToCi = {};
        cis.forEach(ci => { idToCi[ci.id] = ci; });

        cis.forEach(ci => {
            const color = this._colorByType(ci.type);

            nodes.push({ id: ci.id, label: ci.name, title: `${ci.type} | ${ci.status} | ${ci.environment}` , color });

            if (ci.parentId && idToCi[ci.parentId]) {
                edges.push({ from: ci.parentId, to: ci.id, arrows: 'to', color: '#b0b0b0' });
            }
        });

        return {nodes, edges};
    }

    _renderGraph(el, nodes, edges) {
        // Try to load vis-network via loader; fallback to canvas if unavailable.
        Espo.loader.require(['lib!vis-network'], () => {
            const container = document.createElement('div');
            container.style.height = '420px';
            el.empty().append(container);

            // noinspection JSUnresolvedReference
            const network = new window.vis.Network(container, {nodes, edges}, {
                physics: { stabilization: true },
                nodes: { shape: 'dot', size: 14, font: { color: '#222' } },
                edges: { smooth: true }
            });

            network.on('click', params => {
                if (params.nodes && params.nodes.length) {
                    const id = params.nodes[0];
                    this.getRouter().navigate(`#JSLCi/view/${id}`, {trigger: true});
                }
            });
        }, async () => {
            const ok = await this._loadVisFromCdn();
            if (ok && window.vis && window.vis.Network) {
                // Try again
                this._renderGraph(el, nodes, edges);
                return;
            }
            this._renderCanvasGraph(el, nodes, edges);
        });
    }

    _loadVisFromCdn() {
        return new Promise(resolve => {
            if (window.vis && window.vis.Network) {
                resolve(true);
                return;
            }
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/vis-network/standalone/umd/vis-network.min.js';
            script.async = true;
            script.onload = () => resolve(true);
            script.onerror = () => resolve(false);
            document.head.appendChild(script);
        });
    }

    _renderCanvasGraph(el, nodes, edges) {
        const container = document.createElement('div');
        container.style.height = '420px';
        container.style.position = 'relative';
        const canvas = document.createElement('canvas');
        canvas.width = el.width();
        canvas.height = 420;
        container.appendChild(canvas);
        el.empty().append(container);

        const ctx = canvas.getContext('2d');
        const N = nodes.length;

        // Initialize positions
        const pos = {};
        nodes.forEach((n, i) => {
            pos[n.id] = {
                x: (i + 1) * (canvas.width / (N + 1)),
                y: (Math.random() * 0.6 + 0.2) * canvas.height,
                vx: 0, vy: 0,
                r: 9,
                color: n.color,
                label: n.label,
                title: n.title
            };
        });

        const E = edges.map(e => ({from: e.from, to: e.to}));
        const k = Math.sqrt((canvas.width * canvas.height) / Math.max(1, N));
        const iterations = Math.min(400, 60 + 10 * N);
        const area = canvas.width * canvas.height;
        const coolStart = 0.1;

        function fa(d) { return (d * d) / k; }
        function fr(d) { return (k * k) / (d || 1); }

        for (let t = 0; t < iterations; t++) {
            const disp = {};
            nodes.forEach(n => disp[n.id] = {x: 0, y: 0});

            // Repulsion
            for (let i = 0; i < N; i++) {
                for (let j = i + 1; j < N; j++) {
                    const ni = nodes[i].id; const nj = nodes[j].id;
                    const dx = pos[ni].x - pos[nj].x;
                    const dy = pos[ni].y - pos[nj].y;
                    const dist = Math.hypot(dx, dy) || 0.001;
                    const force = fr(dist);
                    const ux = dx / dist, uy = dy / dist;
                    disp[ni].x += ux * force;
                    disp[ni].y += uy * force;
                    disp[nj].x -= ux * force;
                    disp[nj].y -= uy * force;
                }
            }

            // Attraction
            E.forEach(e => {
                const dx = pos[e.from].x - pos[e.to].x;
                const dy = pos[e.from].y - pos[e.to].y;
                const dist = Math.hypot(dx, dy) || 0.001;
                const force = fa(dist);
                const ux = dx / dist, uy = dy / dist;
                disp[e.from].x -= ux * force;
                disp[e.from].y -= uy * force;
                disp[e.to].x += ux * force;
                disp[e.to].y += uy * force;
            });

            // Limit temperature
            const temp = coolStart * (1 - t / iterations);
            nodes.forEach(n => {
                const d = Math.hypot(disp[n.id].x, disp[n.id].y) || 1;
                pos[n.id].x += (disp[n.id].x / d) * Math.min(temp * 10, d);
                pos[n.id].y += (disp[n.id].y / d) * Math.min(temp * 10, d);
                // Keep within bounds
                pos[n.id].x = Math.max(20, Math.min(canvas.width - 20, pos[n.id].x));
                pos[n.id].y = Math.max(20, Math.min(canvas.height - 20, pos[n.id].y));
            });
        }

        // Interactions: click to open
        canvas.addEventListener('click', (ev) => {
            const rect = canvas.getBoundingClientRect();
            const x = ev.clientX - rect.left;
            const y = ev.clientY - rect.top;
            // Find nearest node within radius
            let hit = null; let best = 9999;
            nodes.forEach(n => {
                const p = pos[n.id];
                const d = Math.hypot(p.x - x, p.y - y);
                if (d < p.r + 6 && d < best) { hit = n; best = d; }
            });
            if (hit) {
                this.getRouter().navigate(`#JSLCi/view/${hit.id}`, {trigger: true});
            }
        });

        const draw = () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.save();
            ctx.lineWidth = 1;
            ctx.strokeStyle = '#c0c0c0';
            // edges
            E.forEach(e => {
                const a = pos[e.from];
                const b = pos[e.to];
                ctx.beginPath();
                ctx.moveTo(a.x, a.y);
                ctx.lineTo(b.x, b.y);
                ctx.stroke();
            });

            // nodes
            nodes.forEach(n => {
                const p = pos[n.id];
                ctx.beginPath();
                ctx.fillStyle = p.color || '#888';
                ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                ctx.fill();

                ctx.fillStyle = '#222';
                ctx.font = '12px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(n.label, p.x, p.y - (p.r + 8));
            });
            ctx.restore();
        };

        draw();

        // expose exporter
        el.data('jslCanvas', canvas);
    }

    _colorByType(type) {
        const map = {
            BusinessService: '#5cb85c',
            Application: '#337ab7',
            Database: '#8a6d3b',
            Server: '#d9534f',
            Network: '#5bc0de',
            Storage: '#f0ad4e',
            Other: '#999'
        };
        return map[type] || '#888';
    }

    _exportGraphImage(container) {
        // Try vis first
        const visCanvas = container.querySelector('canvas');
        if (visCanvas && visCanvas.toDataURL) {
            const url = visCanvas.toDataURL('image/png');
            this._downloadUrl(url, 'cmdb-graph.png');
            return;
        }

        // Fallback to our canvas
        const $container = $(container);
        const canvas = $container.data('jslCanvas');
        if (canvas && canvas.toDataURL) {
            const url = canvas.toDataURL('image/png');
            this._downloadUrl(url, 'cmdb-graph.png');
        }
    }

    _downloadUrl(url, filename) {
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }
}
