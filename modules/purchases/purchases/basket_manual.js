/**
 * Purchase Basket - Manual Entry JS
 * Version: 4.6
 * - Bidirectional SAR <-> YER conversion on all discount and financial fields.
 * - Each field shows a live SAR badge next to it when YER is entered, and vice versa.
 * - Auto-converts using the exchange rate input.
 */

console.log('🚀 Basket Manual JS Loaded (v4.6)');

// ============================================
// INITIALIZATION
// ============================================

document.addEventListener('DOMContentLoaded', function () {
    console.log('✅ DOM Content Loaded');

    // --- FINANCIAL CALCULATION SETUP ---
    const financialInputs = [
        'sarInput', 'subtotalInput', 'shippingCost', 'taxRate', 'manualDiscountInput',
        'points_discount', 'club_discount', 'yerExchangeRateInput',
        'subtotalCurrencyDisplay', 'shippingCurrencyDisplay', 'taxCurrencyDisplay',
        'manualDiscountCurrencyDisplay', 'pointsDiscountCurrencyDisplay',
        'clubDiscountCurrencyDisplay', 'totalDiscountCurrencyDisplay', 'grandTotalDisplay'
    ];
    financialInputs.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('input', updateTotals);
        }
    });
    const taxIncludedCheckbox = document.getElementById('taxIncluded');
    if (taxIncludedCheckbox) {
        taxIncludedCheckbox.addEventListener('change', updateTotals);
    }

    // --- BIDIRECTIONAL CONVERSION SETUP ---
    // Each pair: [SAR input id, YER input id, SAR badge id, YER badge id]
    window._biPairs = [
        { sar: 'manualDiscountInput',      yer: 'manualDiscountCurrencyDisplay',   sarBadge: 'manualDiscount_sarBadge',   yerBadge: 'manualDiscount_yerBadge'   },
        { sar: 'points_discount',           yer: 'pointsDiscountCurrencyDisplay',   sarBadge: 'pointsDiscount_sarBadge',   yerBadge: 'pointsDiscount_yerBadge'   },
        { sar: 'club_discount',             yer: 'clubDiscountCurrencyDisplay',     sarBadge: 'clubDiscount_sarBadge',     yerBadge: 'clubDiscount_yerBadge'     },
        { sar: 'subtotalInput',             yer: 'subtotalCurrencyDisplay',         sarBadge: 'subtotal_sarBadge',         yerBadge: 'subtotal_yerBadge'         },
        { sar: 'shippingCost',              yer: 'shippingCurrencyDisplay',         sarBadge: 'shipping_sarBadge',         yerBadge: 'shipping_yerBadge'         },
    ];

    // Inject SAR/YER badge spans next to each field if not already present
    window._biPairs.forEach(pair => {
        injectBadge(pair.sar, pair.sarBadge, 'sar');
        injectBadge(pair.yer, pair.yerBadge, 'yer');
    });

    updateTotals(); // Initial calculation

    // --- PAYMENT SOURCE SELECTION LOGIC ---
    const paymentTypeSelect = document.getElementById('paymentSourceType');
    const paymentDetailsContainer = document.getElementById('paymentSourceDetails');
    const bankSelectorContainer = document.getElementById('bankAccountSelector');
    const cardSelectorContainer = document.getElementById('purchaseCardSelector');
    const bankSelect = document.getElementById('bankAccountSelect');
    const cardSelect = document.getElementById('purchaseCardSelect');
    const balanceDisplayContainer = document.getElementById('sourceBalanceContainer');
    const balanceDisplay = document.getElementById('sourceBalanceDisplay');
// --- IMAGE PREVIEW LOGIC ---
const attachmentInput = document.getElementById('attachment');
const previewContainer = document.getElementById('imagePreviewContainer');

if (attachmentInput && previewContainer) {
    attachmentInput.addEventListener('change', function() {
        // Clear existing previews
        previewContainer.innerHTML = '';

        if (this.files) {
            Array.from(this.files).forEach(file => {
                // Only process image files
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();

                    reader.onload = function(e) {
                        // Create preview element
                        const div = document.createElement('div');
                        div.className = 'preview-item';
                        
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        
                        div.appendChild(img);
                        previewContainer.appendChild(div);
                    }

                    reader.readAsDataURL(file);
                } else {
                    // Fallback for non-image files (like PDFs)
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    div.style.display = 'flex';
                    div.style.alignItems = 'center';
                    div.style.justifyContent = 'center';
                    div.innerHTML = '<i class="fas fa-file-alt fa-2x" style="color: #6b7280;"></i>';
                    previewContainer.appendChild(div);
                }
            });
        }
    });
}
    function handlePaymentTypeChange() {
        const selectedType = paymentTypeSelect.value;

        // Reset all related fields and hide containers
        paymentDetailsContainer.style.display = 'none';
        bankSelectorContainer.style.display = 'none';
        cardSelectorContainer.style.display = 'none';
        balanceDisplayContainer.style.display = 'none';
        balanceDisplay.textContent = '';
        bankSelect.value = '';
        cardSelect.value = '';

        // Disable the non-relevant select to prevent accidental submission
        bankSelect.disabled = true;
        cardSelect.disabled = true;

        if (selectedType === 'bank_account') {
            paymentDetailsContainer.style.display = 'block';
            bankSelectorContainer.style.display = 'block';
            bankSelect.disabled = false;
        } else if (selectedType === 'purchase_card') {
            paymentDetailsContainer.style.display = 'block';
            cardSelectorContainer.style.display = 'block';
            cardSelect.disabled = false;
        }
    }

    function updateSourceBalance(selectElement) {
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const balance = selectedOption.getAttribute('data-balance');

        if (balance) {
            balanceDisplay.innerHTML = `<i class="fas fa-wallet"></i> Available Balance: ${formatMoney(balance)}`;
            balanceDisplayContainer.style.display = 'block';
        } else {
            balanceDisplayContainer.style.display = 'none';
            balanceDisplay.textContent = '';
        }
    }

    if (paymentTypeSelect) {
        paymentTypeSelect.addEventListener('change', handlePaymentTypeChange);
    }
    if (bankSelect) {
        bankSelect.addEventListener('change', () => updateSourceBalance(bankSelect));
    }
    if (cardSelect) {
        cardSelect.addEventListener('change', () => updateSourceBalance(cardSelect));
    }

    handlePaymentTypeChange(); // Run on page load

    // --- DROPDOWN SEARCH/FILTER LOGIC ---
    function setupSearchableDropdown(searchInputId, selectElementId) {
        const searchInput = document.getElementById(searchInputId);
        const selectElement = document.getElementById(selectElementId);

        if (!searchInput || !selectElement) return;

        searchInput.addEventListener('input', function () {
            const searchTerm = this.value.toLowerCase().trim();
            const options = selectElement.getElementsByTagName('option');

            for (let i = 0; i < options.length; i++) {
                const option = options[i];
                const optionText = option.textContent.toLowerCase();
                if (option.value === '') {
                    option.style.display = '';
                    continue;
                }
                if (optionText.includes(searchTerm)) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            }
        });
    }

    // Initialize the search for both dropdowns
    setupSearchableDropdown('bankAccountSearch', 'bankAccountSelect');
    setupSearchableDropdown('purchaseCardSearch', 'purchaseCardSelect');


    console.log('✅ Initialization Complete');
});


// ============================================
// TOTALS CALCULATION ENGINE
// ============================================

function getRate() {
    const r = parseFloat(document.getElementById('yerExchangeRateInput')?.value) || 140;
    return r > 0 ? r : 140;
}

function updateTotals() {
    const rate = getRate();
    const sarInput = document.getElementById('sarInput');
    const subtotalInput = document.getElementById('subtotalInput');

    // SAR main input -> YER subtotal (only if SAR field is active)
    if (sarInput && document.activeElement === sarInput) {
        const sarVal = parseFloat(sarInput.value) || 0;
        subtotalInput.value = (sarVal * rate).toFixed(2);
    }
    // If subtotalInput is active, update sarInput
    if (subtotalInput && document.activeElement === subtotalInput) {
        const yerVal = parseFloat(subtotalInput.value) || 0;
        sarInput.value = (yerVal / rate).toFixed(4);
    }

    const subtotal     = parseFloat(subtotalInput?.value) || 0;
    const shippingCost = parseFloat(document.getElementById('shippingCost')?.value) || 0;
    const taxRate      = parseFloat(document.getElementById('taxRate')?.value) || 0;
    const taxIncluded  = document.getElementById('taxIncluded')?.checked;

    const manualDiscount = parseFloat(document.getElementById('manualDiscountInput')?.value) || 0;
    const pointsDiscount = parseFloat(document.getElementById('points_discount')?.value) || 0;
    const clubDiscount   = parseFloat(document.getElementById('club_discount')?.value) || 0;

    const totalDiscount = manualDiscount + pointsDiscount + clubDiscount;
    const baseForTax    = subtotal - totalDiscount;
    let taxAmount = 0;
    let grandTotal = 0;

    if (taxIncluded) {
        taxAmount  = (baseForTax * taxRate) / (100 + taxRate);
        grandTotal = baseForTax + shippingCost;
    } else {
        taxAmount  = baseForTax * (taxRate / 100);
        grandTotal = baseForTax + taxAmount + shippingCost;
    }

    // --- Update display elements ---
    const totalDiscountDisplayEl = document.getElementById('totalDiscountDisplay');
    if (totalDiscountDisplayEl) totalDiscountDisplayEl.textContent = formatMoney(totalDiscount);
    const taxAmountDisplayEl = document.getElementById('taxAmountDisplay');
    if (taxAmountDisplayEl) taxAmountDisplayEl.textContent = formatMoney(taxAmount);

    // YER column fields (auto-fill from SAR values unless user is editing them)
    setCurrencyInput('subtotalCurrencyDisplay',         subtotal,       rate);
    setCurrencyInput('shippingCurrencyDisplay',          shippingCost,   rate);
    setCurrencyInput('taxCurrencyDisplay',               taxAmount,      rate);
    setCurrencyInput('manualDiscountCurrencyDisplay',    manualDiscount, rate);
    setCurrencyInput('pointsDiscountCurrencyDisplay',    pointsDiscount, rate);
    setCurrencyInput('clubDiscountCurrencyDisplay',      clubDiscount,   rate);
    setCurrencyInput('totalDiscountCurrencyDisplay',     totalDiscount,  rate);
    setCurrencyInput('grandTotalDisplay',                grandTotal,     rate);

    // Auto-fill final price override
    const finalOverride = document.getElementById('final_price_override');
    if (finalOverride && document.activeElement !== finalOverride) {
        finalOverride.value = grandTotal.toFixed(2);
    }

    // Update all conversion badges
    updateAllBadges(rate);
}

// ============================================
// BIDIRECTIONAL BADGE SYSTEM
// ============================================

/**
 * Injects a conversion badge span immediately after an input element.
 * type: 'sar' means this input holds YER and badge shows SAR equivalent
 *       'yer' means this input holds SAR and badge shows YER equivalent
 */
function injectBadge(inputId, badgeId, type) {
    const input = document.getElementById(inputId);
    if (!input || document.getElementById(badgeId)) return;

    const badge = document.createElement('span');
    badge.id = badgeId;
    badge.className = 'currency-convert-badge';
    badge.setAttribute('data-type', type);
    badge.setAttribute('data-for', inputId);
    badge.style.cssText = [
        'display:inline-block',
        'font-size:11px',
        'font-weight:700',
        'padding:2px 7px',
        'border-radius:10px',
        'margin-top:4px',
        'white-space:nowrap',
        'transition:opacity .2s',
        type === 'sar'
            ? 'background:#e6f4ea;color:#1d6f42;border:1px solid #b7dfbf'    // green = SAR
            : 'background:#fef3c7;color:#92400e;border:1px solid #fcd34d',   // amber = YER
    ].join(';');

    // Insert badge below the input by wrapping it or appending after
    const parent = input.parentNode;
    const wrapper = document.createElement('div');
    wrapper.style.cssText = 'display:flex;flex-direction:column;align-items:flex-end;gap:2px;';
    parent.insertBefore(wrapper, input);
    wrapper.appendChild(input);
    wrapper.appendChild(badge);
}

function updateAllBadges(rate) {
    if (!window._biPairs) return;
    window._biPairs.forEach(pair => {
        const sarVal = parseFloat(document.getElementById(pair.sar)?.value) || 0;
        const yerVal = parseFloat(document.getElementById(pair.yer)?.value) || 0;

        // Badge next to SAR field: show YER equivalent
        const sarBadge = document.getElementById(pair.sarBadge);
        if (sarBadge) {
            const yerEquiv = sarVal * rate;
            sarBadge.textContent = sarVal > 0 ? `= ${yerEquiv.toLocaleString('en-US', {minimumFractionDigits:0, maximumFractionDigits:0})} YER` : '';
            sarBadge.style.opacity = sarVal > 0 ? '1' : '0';
        }

        // Badge next to YER field: show SAR equivalent
        const yerBadge = document.getElementById(pair.yerBadge);
        if (yerBadge) {
            const sarEquiv = rate > 0 ? yerVal / rate : 0;
            yerBadge.textContent = yerVal > 0 ? `= ${sarEquiv.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})} SAR` : '';
            yerBadge.style.opacity = yerVal > 0 ? '1' : '0';
        }
    });

    // Special: main SAR input -> show YER on its own badge
    const sarMainInput = document.getElementById('sarInput');
    const sarMainBadge = document.getElementById('sarMain_yerBadge');
    if (sarMainInput && sarMainBadge) {
        const v = parseFloat(sarMainInput.value) || 0;
        sarMainBadge.textContent = v > 0 ? `= ${(v * rate).toLocaleString('en-US', {minimumFractionDigits:0, maximumFractionDigits:0})} YER` : '';
        sarMainBadge.style.opacity = v > 0 ? '1' : '0';
    }
}

// Also handle YER->SAR bidirectional for YER column inputs
document.addEventListener('DOMContentLoaded', function() {
    // Wire up YER inputs to reverse-convert back into SAR fields
    const reverseMap = {
        'manualDiscountCurrencyDisplay': 'manualDiscountInput',
        'pointsDiscountCurrencyDisplay': 'points_discount',
        'clubDiscountCurrencyDisplay':   'club_discount',
        'shippingCurrencyDisplay':       'shippingCost',
        'subtotalCurrencyDisplay':       'subtotalInput',
    };
    Object.entries(reverseMap).forEach(([yerId, sarId]) => {
        const yerEl = document.getElementById(yerId);
        if (yerEl) {
            yerEl.addEventListener('input', function() {
                const rate = getRate();
                const yerVal = parseFloat(this.value) || 0;
                const sarEl = document.getElementById(sarId);
                if (sarEl && document.activeElement === yerEl) {
                    sarEl.value = (yerVal / rate).toFixed(4);
                }
                updateTotals();
            });
        }
    });

    // Inject badge for the main SAR input too
    const sarMainInput = document.getElementById('sarInput');
    if (sarMainInput && !document.getElementById('sarMain_yerBadge')) {
        const badge = document.createElement('span');
        badge.id = 'sarMain_yerBadge';
        badge.style.cssText = 'display:inline-block;font-size:11px;font-weight:700;padding:2px 7px;border-radius:10px;margin-top:4px;white-space:nowrap;background:#fef3c7;color:#92400e;border:1px solid #fcd34d;transition:opacity .2s;opacity:0;';
        const parent = sarMainInput.parentNode;
        const wrapper = document.createElement('div');
        wrapper.style.cssText = 'display:flex;flex-direction:column;align-items:flex-end;gap:2px;';
        // Find the existing flex wrapper that has the SAR flag badge
        // The input is inside a div with display:flex already - just append badge there
        parent.appendChild(badge);
    }
}, { once: true });


// ============================================
// UTILITY FUNCTIONS
// ============================================
function formatMoney(amount) {
    if (amount === null || isNaN(amount)) return '0.00 YER';

    // Options to ensure two decimal places are always shown
    const options = {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    };

    // 'en-US' locale uses standard Western Arabic numerals (0, 1, 2...)
    return new Intl.NumberFormat('en-US', options).format(parseFloat(amount)) + ' YER';
}

function setCurrencyInput(elementId, yerAmount, exchangeRate) {
    const element = document.getElementById(elementId);
    if (!element) return;
    if (document.activeElement === element) return;
    const normalizedRate = exchangeRate > 0 ? exchangeRate : 140;
    const value = yerAmount;
    element.value = Number.isFinite(value) ? value.toFixed(2) : '0.00';
}

function formatSarMoney(amount) {
    if (amount === null || isNaN(amount)) return '0.00 SAR';
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(parseFloat(amount)) + ' SAR';
}
