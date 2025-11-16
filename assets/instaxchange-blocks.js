(function () {
  "use strict";

  // Enhanced WooCommerce blocks integration for InstaxChange
  if (
    typeof window.wc === "undefined" ||
    typeof window.wc.wcBlocksRegistry === "undefined" ||
    typeof window.wp === "undefined"
  ) {
    return;
  }

  const { createElement: e, Fragment } = window.wp.element;
  const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
  const { getSetting } = window.wc.wcSettings;
  const { __ } = window.wp.i18n;
  
  // Get payment method data from server
  const settings = getSetting('instaxchange_data', {});

  // Payment method content component
  const InstaxChangeContent = (props) => {
    const { eventRegistration, emitResponse } = props;
    
    return e(
      "div",
      {
        className: "wc-block-checkout__instaxchange-content",
        style: {
          padding: "16px",
          backgroundColor: "#f8f9fa",
          border: "2px solid #007cba",
          borderRadius: "8px",
          margin: "16px 0",
        },
      },
      [
        e(
          "h4",
          {
            key: "title",
            style: {
              margin: "0 0 12px 0",
              color: "#007cba",
              fontSize: "16px",
              fontWeight: "600",
            },
          },
          __("All Payment Methods Available", "wc-instaxchange")
        ),
        e(
          "p",
          {
            key: "desc",
            style: {
              margin: "0 0 16px 0",
              fontSize: "14px",
              lineHeight: "1.5",
              color: "#666",
            },
          },
          settings.description || __("Secure payments with credit/debit cards, digital wallets, bank transfers, and cryptocurrency.", "wc-instaxchange")
        ),
        e(
          "div",
          {
            key: "badges",
            style: {
              display: "grid",
              gridTemplateColumns: "repeat(auto-fit, minmax(120px, 1fr))",
              gap: "8px",
            },
          },
          [
            e("div", { key: "1", className: "instax-badge" }, __("💳 Cards", "wc-instaxchange")),
            e("div", { key: "2", className: "instax-badge" }, __("📱 Digital Wallets", "wc-instaxchange")),
            e("div", { key: "3", className: "instax-badge" }, __("🏦 Bank Transfer", "wc-instaxchange")),
            e("div", { key: "4", className: "instax-badge" }, __("₿ Crypto", "wc-instaxchange")),
          ]
        ),
        settings.testMode && e(
          "div",
          {
            key: "testmode",
            style: {
              marginTop: "12px",
              padding: "8px 12px",
              backgroundColor: "#fff3cd",
              border: "1px solid #ffeaa7",
              borderRadius: "4px",
              fontSize: "12px",
              color: "#856404",
            },
          },
          __("⚠️ Test Mode Enabled", "wc-instaxchange")
        ),
      ]
    );
  };

  // Payment method label component
  const InstaxChangeLabel = () => {
    return e(
      "span",
      {
        style: {
          fontWeight: "600",
          fontSize: "16px",
          display: "flex",
          alignItems: "center",
        },
      },
      [
        e("span", { key: "icon", style: { marginRight: "8px" } }, "🚀"),
        settings.title || __("Pay with InstaxChange - All Methods Available", "wc-instaxchange"),
      ]
    );
  };

  // Payment method configuration
  const paymentMethodConfig = {
    name: "instaxchange",
    label: e(InstaxChangeLabel),
    content: e(InstaxChangeContent),
    edit: e(InstaxChangeContent),
    canMakePayment: () => {
      // Check if the payment method is available
      return Promise.resolve(settings.enabled !== false);
    },
    paymentMethodId: "instaxchange",
    ariaLabel: settings.title || __("Pay with InstaxChange - All Methods Available", "wc-instaxchange"),
    supports: {
      features: settings.supports || ["products"],
      showSavedCards: false,
      showSaveOption: false,
    },
  };

  // Register the payment method
  try {
    registerPaymentMethod(paymentMethodConfig);
  } catch (error) {
    console.error("InstaxChange Blocks: Registration failed:", error);
  }

  // Enhanced CSS for blocks
  const style = document.createElement("style");
  style.textContent = `
    /* InstaxChange Blocks Styles */
    .instax-badge {
      background: #e7f3ff;
      color: #0056b3;
      padding: 6px 10px;
      border-radius: 16px;
      font-size: 11px;
      font-weight: 600;
      text-align: center;
      transition: all 0.3s ease;
      border: 1px solid transparent;
      cursor: pointer;
    }
    
    .instax-badge:hover {
      background: #667eea;
      color: white;
      transform: translateY(-1px);
      box-shadow: 0 2px 4px rgba(102, 126, 234, 0.3);
    }
    
    /* Ensure payment method visibility */
    [data-payment-method="instaxchange"] {
      display: block !important;
      visibility: visible !important;
      opacity: 1 !important;
    }
    
    .wc-block-components-radio-control-accordion-option[data-payment-method="instaxchange"] {
      border: 2px solid #e1e5e9 !important;
      border-radius: 8px !important;
      margin-bottom: 12px !important;
      padding: 16px !important;
      background: #ffffff !important;
      transition: all 0.3s ease !important;
    }
    
    .wc-block-components-radio-control-accordion-option[data-payment-method="instaxchange"]:hover {
      border-color: #007cba !important;
      box-shadow: 0 2px 8px rgba(0, 124, 186, 0.1) !important;
      transform: translateY(-1px) !important;
    }
    
    .wc-block-components-radio-control-accordion-option[data-payment-method="instaxchange"].wc-block-components-radio-control-accordion-option--checked {
      border-color: #007cba !important;
      background: linear-gradient(135deg, #f0f8ff 0%, #ffffff 100%) !important;
      box-shadow: 0 4px 12px rgba(0, 124, 186, 0.15) !important;
    }
    
    /* Content area styling */
    .wc-block-checkout__instaxchange-content {
      font-family: inherit;
      line-height: 1.6;
    }
    
    /* Responsive design */
    @media (max-width: 768px) {
      .wc-block-checkout__instaxchange-content [style*="display: grid"] {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 6px !important;
      }
      
      .instax-badge {
        padding: 4px 8px !important;
        font-size: 10px !important;
      }
    }
    
    @media (max-width: 480px) {
      .wc-block-checkout__instaxchange-content [style*="display: grid"] {
        grid-template-columns: 1fr !important;
      }
    }
  `;
  
  document.head.appendChild(style);
  
  // Fallback registration method - try again after DOM is loaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      setTimeout(() => {
        if (typeof window.wc?.wcBlocksRegistry?.registerPaymentMethod === 'function') {
          try {
            window.wc.wcBlocksRegistry.registerPaymentMethod(paymentMethodConfig);
          } catch (e) {
            // Silent fallback
          }
        }
      }, 500);
    });
  }

  // Additional fallback for late-loading scenarios
  window.addEventListener('load', function() {
    setTimeout(() => {
      if (typeof window.wc?.wcBlocksRegistry?.registerPaymentMethod === 'function') {
        try {
          const methods = window.wc?.wcSettings?.availablePaymentMethods || {};
          if (!methods.instaxchange) {
            window.wc.wcBlocksRegistry.registerPaymentMethod(paymentMethodConfig);
          }
        } catch (e) {
          // Silent fallback
        }
      }
    }, 1000);
  });
})();
