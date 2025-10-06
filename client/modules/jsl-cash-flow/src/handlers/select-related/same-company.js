/************************************************************************
 * JSL Cash Flow - select contacts filtered by selected company field.
 ************************************************************************/
import SelectRelatedHandler from 'handlers/select-related';

export default class SameCompanySelectRelatedHandler extends SelectRelatedHandler {
    getFilters(model) {
        const advanced = {};

        // Determine which company field is present on the form to filter contacts
        const pairs = [
            {companyFieldId: 'providerCompanyId', fieldName: 'providerContact'},
            {companyFieldId: 'clientCompanyId', fieldName: 'clientContact'},
            {companyFieldId: 'companyId', fieldName: 'contact'}
        ];

        let accountId = null;
        let accountName = null;

        for (const p of pairs) {
            if (model.get(p.companyFieldId)) {
                accountId = model.get(p.companyFieldId);
                accountName = model.get(p.companyFieldId.replace('Id', 'Name'));
                break;
            }
        }

        if (accountId) {
            // Filter contacts: account equals selected account
            advanced.account = {
                attribute: 'accountId',
                type: 'equals',
                value: accountId,
                data: {
                    type: 'is',
                    nameValue: accountName,
                },
            };
        }

        return Promise.resolve({ advanced });
    }
}
