// path: custom/Espo/Modules/SRNCashFlow/Resources/client/js/views/fields/srn-contact2.js
define('srn:views/fields/srn-contact2', ['views/fields/link'], function (Dep) {
    return Dep.extend({
        setup: function () {
            Dep.prototype.setup.call(this);
            this.listenTo(this.model, 'change:company2Id', () => {
                this.model.set('contact2Id', null);
                this.model.set('contact2Name', null);
            });
        },
        getSelectFilters: function () {
            const filters = Dep.prototype.getSelectFilters.call(this) || {};
            const accountId = this.model.get('company2Id');
            if (accountId) {
                filters.accountId = accountId;
            }
            return filters;
        }
    });
});