define('quote-management:views/quote/record/detail', ['views/record/detail'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.buttonList = Espo.Utils.clone(this.buttonList || []);

            this.buttonList.push({
                name: 'duplicateAsNewVersion',
                label: 'Duplicate as New Version',
                style: 'primary',
            });
        },

        actionDuplicateAsNewVersion: function () {
            this.confirm(
                this.translate('confirmDuplicate', 'messages', 'Quote'),
                () => {
                    Espo.Ui.notify(this.translate('duplicating', 'messages', 'Quote'));

                    Espo.Ajax.postRequest('Quote/' + this.model.id + '/duplicateAsNewVersion').then(response => {
                        Espo.Ui.success(this.translate('duplicated', 'messages', 'Quote'));

                        this.getRouter().navigate('#Quote/view/' + response.id, {trigger: true});
                    }).catch(() => {
                        Espo.Ui.error(this.translate('duplicateFailed', 'messages', 'Quote'));
                    });
                }
            );
        },
    });
});
