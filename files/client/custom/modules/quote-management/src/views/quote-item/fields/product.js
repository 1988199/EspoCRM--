define('quote-management:views/quote-item/fields/product', ['views/fields/link'], function (Dep) {
    return Dep.extend({
        setup: function () {
            Dep.prototype.setup.call(this);
            this.listenTo(this.model, 'change:catalogProductId', () => {
                const id = this.model.get('catalogProductId');
                if (!id) return;
                // 选择产品只带入名称与规格；本项目单价始终由录入人员填写。
                Espo.Ajax.getRequest('CProduct/' + encodeURIComponent(id)).then(product => {
                    if (this.model.get('catalogProductId') !== id) return;
                    this.model.set({productName: product.name, specification: product.spec || ''});
                }).catch(() => Espo.Ui.error('无法读取产品，请重新选择或填写产品名称。'));
            });
        },
    });
});
