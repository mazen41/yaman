/*
 * Shared cart storage for products.php and product.php.
 * Both pages read and write the same localStorage key, so the cart is one cart.
 */
(function (window) {
    'use strict';

    function storageKey(customerId) {
        return 'cart_' + customerId;
    }

    function normalizeItem(item) {
        if (!item || !item.cartKey) return null;
        const price = Number(item.price) || 0;
        const originalPrice = Number(item.originalPrice) || price;
        const qty = parseInt(item.qty, 10) || 0;
        if (qty <= 0) return null;
        return {
            cartKey: String(item.cartKey),
            productId: item.productId,
            name: item.name || '',
            price: price,
            originalPrice: originalPrice,
            qty: qty,
            variantText: item.variantText || '',
            img: item.img || ''
        };
    }

    window.YamanCart = {
        load: function (customerId) {
            try {
                const raw = localStorage.getItem(storageKey(customerId));
                const list = raw ? JSON.parse(raw) : [];
                if (!Array.isArray(list)) return [];
                return list.map(normalizeItem).filter(Boolean);
            } catch (e) {
                return [];
            }
        },
        save: function (customerId, cart) {
            try {
                localStorage.setItem(storageKey(customerId), JSON.stringify(cart));
            } catch (e) { /* storage unavailable: keep in memory only */ }
        }
    };
})(window);
