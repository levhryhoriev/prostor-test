define([
    'Magento_Checkout/js/view/summary/abstract-total',
    'Magento_Checkout/js/model/quote'
], function (Component, quote) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Prostor_CumDiscount/cart/totals/cumulative'
        },

        getSegment: function () {
            var totals = quote.getTotals()();
            var segments = totals && totals.total_segments ? totals.total_segments : [];

            return segments.find(function (segment) {
                return segment.code === 'prostor_cumdiscount';
            });
        },

        isDisplayed: function () {
            var segment = this.getSegment();

            return Boolean(segment) && Number(segment.value) !== 0;
        },

        getTitle: function () {
            return this.getSegment().title;
        },

        getValue: function () {
            return this.getFormattedPrice(this.getSegment().value);
        }
    });
});
