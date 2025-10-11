// path: custom/Espo/Modules/SRNCashFlow/Resources/client/js/views/fields/srn-contact1.js
define('srn:views/fields/srn-contact1', ['views/fields/link'], function (Dep) {
    return Dep.extend({
        setup: function () {
            Dep.prototype.setup.call(this);
            this.listenTo(this.model, 'change:company1Id', () => {
                this.model.set('contact1Id', null);
                this.model.set('contact1Name', null);
            });
        },
        getSelectFilters: function () {
            const filters = Dep.prototype.getSelectFilters.call(this) || {};
            const accountId = this.model.get('company1Id');
            if (accountId) {
                filters.accountId = accountId;
            }
            return filters;
        }
    });
});