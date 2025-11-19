/**
 * InstaxChange Checkout Integration JavaScript
 * Handles payment method selection and order processing on checkout page
 */

(function ($) {
  "use strict";

  // Global variables
  let currentMethod = "card";

  /**
   * Initialize checkout integration
   */
  function initCheckoutIntegration() {
    console.log(
      "InstaxChange: Checkout integration ready - WooCommerce will handle payment processing"
    );

    // Handle InstaxChange payment method selection
    $(document).on(
      "change",
      'input[name="payment_method"][value="instaxchange"]',
      function () {
        if ($(this).is(":checked")) {
          console.log("InstaxChange: Payment method selected");

          // Update order button text
          var placeOrderBtn = $(
            '#place_order, .button[name="woocommerce_checkout_place_order"]'
          );
          if (placeOrderBtn.length) {
            placeOrderBtn.val("Proceed with InstaxChange Payment");
            placeOrderBtn.text("Proceed with InstaxChange Payment");
          }
        }
      }
    );

    // Force show gateway function for debugging
    window.forceShowInstaxChange = function () {
      console.log("InstaxChange: Force showing gateway...");

      // Look for existing gateway
      var existingGateway = $(
        'input[name="payment_method"][value="instaxchange"]'
      ).closest("li");
      if (existingGateway.length) {
        console.log("InstaxChange: Found existing gateway, forcing visibility");
        existingGateway.show().css({
          display: "block !important",
          visibility: "visible !important",
          opacity: "1 !important",
          height: "auto !important",
          position: "relative !important",
          "z-index": "1 !important",
        });

        // Make sure input is clickable
        existingGateway.find("input").css({
          display: "inline-block !important",
          "pointer-events": "auto !important",
          cursor: "pointer !important",
        });

        return;
      }

      // If not found, try emergency injection
      console.log(
        "InstaxChange: Gateway not found, attempting emergency injection"
      );

      var containers = [
        ".woocommerce-checkout-payment ul",
        ".wc_payment_methods",
        ".payment_methods",
        "#payment ul",
        "ul.payment_methods",
      ];

      var injected = false;
      for (var i = 0; i < containers.length; i++) {
        var container = $(containers[i]);
        if (container.length) {
          var gatewayHtml =
            '<li class="wc_payment_method payment_method_instaxchange" style="display: block !important;">' +
            '<input id="payment_method_instaxchange" type="radio" class="input-radio" name="payment_method" value="instaxchange" checked="checked" style="display: inline-block !important;">' +
            '<label for="payment_method_instaxchange" style="display: block !important;">' +
            "<strong>🔒 Pay with InstaxChange - All Methods Available</strong><br>" +
            "<small>Secure payments with credit/debit cards, digital wallets, bank transfers, and cryptocurrency.</small>" +
            "</label>" +
            "</li>";

          container.append(gatewayHtml);
          console.log("InstaxChange: Emergency injection successful");
          injected = true;
          break;
        }
      }

      if (!injected) {
        // Last resort: inject anywhere
        var checkoutForm = $("form.checkout, .woocommerce-checkout").first();
        if (checkoutForm.length) {
          checkoutForm.append(
            '<div style="margin-top: 20px; padding: 20px; border: 1px solid #ddd; background: #f9f9f9;"><h3>Payment Method</h3><p><input type="radio" name="payment_method" value="instaxchange" checked> <strong>🔒 Pay with InstaxChange</strong></p></div>'
          );
          console.log("InstaxChange: Last resort injection successful");
        }
      }
    };

    // Let WooCommerce handle the payment processing normally
    // The gateway's process_payment method will be called automatically when the form is submitted
  }

  /**
   * Show fallback warning popup
   */
  function showFallbackWarningPopup(originalMethod, fallbackMethod) {
    // Create modal overlay
    const modalOverlay = document.createElement("div");
    modalOverlay.className = "fallback-warning-modal-overlay";
    modalOverlay.innerHTML = `
            <div class="fallback-warning-modal">
                <div class="modal-header">
                    <div class="modal-icon">⚠️</div>
                    <h3>Payment Method Not Available</h3>
                </div>
                <div class="modal-content">
                    <p><strong>${originalMethod}</strong> is not available for your region or configuration.</p>
                    <p>We've automatically switched you to <strong>${fallbackMethod}</strong> for a smooth payment experience.</p>
                </div>
                <div class="modal-actions">
                    <button type="button" class="modal-btn primary" onclick="dismissFallbackWarningModal()">Continue with ${fallbackMethod}</button>
                </div>
            </div>
        `;

    // Add to body
    document.body.appendChild(modalOverlay);

    // Show modal with animation
    setTimeout(() => {
      modalOverlay.classList.add("active");
    }, 10);

    // Auto-dismiss after 8 seconds
    setTimeout(() => {
      dismissFallbackWarningModal();
    }, 8000);
  }

  /**
   * Dismiss fallback warning modal
   */
  window.dismissFallbackWarningModal = function () {
    const modalOverlay = document.querySelector(
      ".fallback-warning-modal-overlay"
    );
    if (modalOverlay) {
      modalOverlay.classList.remove("active");
      setTimeout(() => {
        if (modalOverlay.parentNode) {
          modalOverlay.parentNode.removeChild(modalOverlay);
        }
      }, 300);
    }
  };

  // Initialize when document is ready
  $(document).ready(function () {
    initCheckoutIntegration();
  });
})(jQuery);
