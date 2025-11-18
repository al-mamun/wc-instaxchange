/**
 * InstaxChange Theme Fixes JavaScript
 * Handles aggressive gateway injection and theme compatibility fixes
 */

(function ($) {
  "use strict";

  /**
   * Force gateway visibility for theme compatibility
   */
  function forceGatewayVisibility() {
    // Add JavaScript to force gateway visibility
    jQuery(document).ready(function ($) {
      // Force InstaxChange gateway visibility
      setTimeout(function () {
        var gatewayRow = $(
          'input[name="payment_method"][value="instaxchange"]'
        ).closest("li");
        if (gatewayRow.length) {
          gatewayRow.show();
          gatewayRow.css({
            display: "block",
            visibility: "visible",
            opacity: "1",
          });
        }

        // Force payment method buttons visibility
        $(".method-btn").each(function () {
          $(this).show();
          $(this).css({
            display: "flex",
            visibility: "visible",
            opacity: "1",
          });
        });
      }, 1000);
    });
  }

  /**
   * Check if we're on blocks checkout
   */
  function isBlocksCheckout() {
    return $('.wc-block-checkout').length > 0 || $('[data-block-name*="checkout"]').length > 0;
  }

  /**
   * Check if we're on classic checkout
   */
  function isClassicCheckout() {
    return $('.woocommerce-checkout').length > 0 && !isBlocksCheckout();
  }

  /**
   * Aggressive gateway injection for theme compatibility
   */
  function injectGatewayAggressively() {
    console.log("InstaxChange: Checking checkout type...");
    console.log("InstaxChange: Blocks checkout:", isBlocksCheckout());
    console.log("InstaxChange: Classic checkout:", isClassicCheckout());
    
    if (isBlocksCheckout()) {
      console.log("InstaxChange: Blocks checkout detected - gateway should be handled by blocks integration");
      return;
    }
    
    if (!isClassicCheckout()) {
      console.log("InstaxChange: Not on checkout page");
      return;
    }

    // Debug: Log available gateways (only if WC is available)
    if (typeof WC !== 'undefined' && typeof WC().payment_gateways === 'function') {
      var available_gateways = WC()
        .payment_gateways()
        .get_available_payment_gateways();
      var registered_gateways = WC().payment_gateways().payment_gateways();
      var debug_info = {
        available_gateways: Object.keys(available_gateways),
        registered_gateways: Object.keys(registered_gateways),
        instaxchange_registered:
          typeof registered_gateways["instaxchange"] !== "undefined",
        instaxchange_available:
          typeof available_gateways["instaxchange"] !== "undefined",
      };
      console.log("InstaxChange: Checkout page debug", debug_info);
    } else {
      console.log("InstaxChange: WC object not available yet, skipping debug");
    }

    // Force inject InstaxChange gateway directly into DOM
    var injectionScript = `
            jQuery(document).ready(function($) {
                console.log("InstaxChange: Starting aggressive gateway injection...");

                function injectInstaxChangeGateway() {
                    console.log("InstaxChange: Attempting gateway injection...");

                    // Check if already exists
                    if ($("input[name=\"payment_method\"][value=\"instaxchange\"]").length > 0) {
                        console.log("InstaxChange: Gateway already exists in DOM");
                        return;
                    }

                    // Find payment methods container - try multiple selectors
                    var containers = [
                        ".woocommerce-checkout-payment ul",
                        ".wc_payment_methods",
                        ".payment_methods",
                        "#payment ul",
                        "ul.payment_methods",
                        ".checkout_payment ul",
                        "[class*=\"payment\"][class*=\"method\"] ul"
                    ];

                    var container = null;
                    for (var i = 0; i < containers.length; i++) {
                        container = $(containers[i]);
                        if (container.length > 0) {
                            console.log("InstaxChange: Found container with selector:", containers[i]);
                            break;
                        }
                    }

                    if (!container || container.length === 0) {
                        console.log("InstaxChange: No payment container found, trying fallback injection");

                        // Fallback: inject after the last payment method
                        var lastPaymentMethod = $(".woocommerce-checkout-payment li, .wc_payment_method, .payment_method").last();
                        if (lastPaymentMethod.length > 0) {
                            container = lastPaymentMethod.parent();
                            console.log("InstaxChange: Using fallback container");
                        }
                    }

                    if (container && container.length > 0) {
                        var gatewayHtml = '<li class="wc_payment_method payment_method_instaxchange" style="display: block !important; visibility: visible !important; opacity: 1 !important;">' +
                            '<input id="payment_method_instaxchange" type="radio" class="input-radio" name="payment_method" value="instaxchange" style="display: inline-block !important;">' +
                            '<label for="payment_method_instaxchange" style="display: block !important;">' +
                                '<strong>🔒 Pay with InstaxChange - All Methods Available</strong><br>' +
                                '<small>Secure payments with credit/debit cards, digital wallets, bank transfers, and cryptocurrency.</small>' +
                            '</label>' +
                            '<div class="payment_box payment_method_instaxchange" style="display: none;">' +
                                '<p>Experience seamless payments with InstaxChange - supporting all major payment methods worldwide.</p>' +
                            '</div>' +
                        '</li>';

                        container.append(gatewayHtml);
                        console.log("InstaxChange: Gateway injected successfully");

                        // Force visibility with aggressive CSS
                        setTimeout(function() {
                            $("#payment_method_instaxchange").closest("li").css({
                                "display": "block !important",
                                "visibility": "visible !important",
                                "opacity": "1 !important",
                                "height": "auto !important",
                                "overflow": "visible !important",
                                "position": "relative !important",
                                "z-index": "1 !important"
                            });

                            // Make sure the input is clickable
                            $("#payment_method_instaxchange").css({
                                "display": "inline-block !important",
                                "visibility": "visible !important",
                                "opacity": "1 !important",
                                "pointer-events": "auto !important",
                                "cursor": "pointer !important"
                            });

                            console.log("InstaxChange: Gateway visibility forced");
                        }, 100);

                    } else {
                        console.log("InstaxChange: Could not find container to inject into, trying emergency injection");

                        // Emergency injection: find any form element and inject after it
                        var checkoutForm = $('form.checkout, form[name="checkout"], .woocommerce-checkout form').first();
                        if (checkoutForm.length > 0) {
                            // Create a payment methods section
                            var emergencyContainer = '<div class="woocommerce-checkout-payment" style="margin-top: 20px;">' +
                                '<h3>Payment Method</h3>' +
                                '<ul class="wc_payment_methods payment_methods">' +
                                    '<li class="wc_payment_method payment_method_instaxchange" style="display: block !important;">' +
                                        '<input id="payment_method_instaxchange" type="radio" class="input-radio" name="payment_method" value="instaxchange" checked="checked" style="display: inline-block !important;">' +
                                        '<label for="payment_method_instaxchange" style="display: block !important;">' +
                                            '<strong>🔒 Pay with InstaxChange - All Methods Available</strong><br>' +
                                            '<small>Secure payments with credit/debit cards, digital wallets, bank transfers, and cryptocurrency.</small>' +
                                        '</label>' +
                                    '</li>' +
                                '</ul>' +
                            '</div>';

                            checkoutForm.append(emergencyContainer);
                            console.log("InstaxChange: Emergency injection successful");
                        } else {
                            console.log("InstaxChange: Emergency injection failed - no form found");
                        }
                    }
                }

                // Run immediately
                injectInstaxChangeGateway();

                // Run multiple times with delays
                setTimeout(injectInstaxChangeGateway, 500);
                setTimeout(injectInstaxChangeGateway, 1000);
                setTimeout(injectInstaxChangeGateway, 2000);
                setTimeout(injectInstaxChangeGateway, 3000);
                setTimeout(injectInstaxChangeGateway, 5000);

                // Run on WooCommerce events
                $(document.body).on("updated_checkout", function() {
                    console.log("InstaxChange: Checkout updated, reinjecting");
                    setTimeout(injectInstaxChangeGateway, 500);
                });

                // Run on page load
                $(window).on("load", function() {
                    console.log("InstaxChange: Page loaded, final injection");
                    setTimeout(injectInstaxChangeGateway, 1000);
                });
            });
        `;

    // Execute the injection script safely (without eval)
    if (typeof jQuery !== "undefined") {
      // Create a Function from the script string (safer than eval)
      // This still executes code but respects CSP better
      try {
        const executeScript = new Function("$", "jQuery", injectionScript);
        executeScript($, jQuery);
      } catch (error) {
        console.error("InstaxChange: Error executing theme compatibility script:", error);
      }
    }
  }

  /**
   * Force gateway registration for theme compatibility
   */
  function forceGatewayRegistration() {
    if (typeof WC !== "undefined" && WC().payment_gateways) {
      var gateways = WC().payment_gateways();
      if (gateways && !gateways.payment_gateways()["instaxchange"]) {
        console.log("InstaxChange: Force registering gateway");
        // This would need to be handled by PHP, but we can trigger it via AJAX if needed
      }
    }
  }

  /**
   * Initialize theme fixes
   */
  function initThemeFixes() {
    // Only run on checkout page
    if (!$("body").hasClass("woocommerce-checkout")) {
      return;
    }

    console.log("InstaxChange: Initializing theme fixes");

    // Force gateway visibility
    forceGatewayVisibility();

    // Force gateway registration
    forceGatewayRegistration();

    // Run injection after a delay to ensure DOM is ready
    setTimeout(function () {
      injectGatewayAggressively();
    }, 1000);
  }

  // Initialize when document is ready
  $(document).ready(function () {
    initThemeFixes();
  });
})(jQuery);
