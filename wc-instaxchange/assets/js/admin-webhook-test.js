/**
 * InstaxChange Admin Webhook Testing JavaScript
 * Handles webhook endpoint testing in the admin settings
 */

(function ($) {
  "use strict";

  // Make function globally available for the button onclick
  window.testWebhookEndpoint = function () {
    const button = event.target;
    const resultSpan = document.getElementById("webhook-test-result");

    if (!button || !resultSpan) {
      console.error(
        "InstaxChange: Required elements not found for webhook test"
      );
      return;
    }

    button.disabled = true;
    button.textContent = "Testing...";
    resultSpan.innerHTML = "";

    // Prepare AJAX request with nonce
    const requestData = {
      action: "check_webhook_status",
      security: instaxchangeAdmin.nonces.checkWebhookStatus,
    };

    fetch(instaxchangeAdmin.ajaxUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: new URLSearchParams(requestData),
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
      })
      .then((data) => {
        if (data.success) {
          const results = data.data.results;
          let html = "<br><strong>Webhook Test Results:</strong><br>";

          if (results.legacy.accessible) {
            html += "✅ Legacy endpoint accessible<br>";
          } else {
            html += "❌ Legacy endpoint not accessible<br>";
          }

          if (results.rest.accessible) {
            html += "✅ REST API endpoint accessible<br>";
          } else {
            html += "❌ REST API endpoint not accessible<br>";
          }

          html +=
            "<br><strong>Recommended URL:</strong> " +
            data.data.recommended_url;

          resultSpan.innerHTML = html;
        } else {
          resultSpan.innerHTML =
            "<br>❌ Test failed: " + (data.data || "Unknown error");
        }
      })
      .catch((error) => {
        console.error("InstaxChange: Webhook test error:", error);
        resultSpan.innerHTML = "<br>❌ Test error: " + error.message;
      })
      .finally(() => {
        button.disabled = false;
        button.textContent = "Test Webhook Endpoints";
      });
  };

  // Function to check and fix stuck orders
  window.checkStuckOrders = function () {
    const button = event.target;
    const resultDiv = document.getElementById("stuck-orders-result");

    if (!button || !resultDiv) {
      console.error(
        "InstaxChange: Required elements not found for stuck orders check"
      );
      return;
    }

    button.disabled = true;
    button.textContent = "Checking...";
    resultDiv.innerHTML = "<p>🔄 Checking for stuck orders...</p>";

    // Prepare AJAX request with nonce
    const requestData = {
      action: "check_stuck_orders",
      security: instaxchangeAdmin.nonces.checkStuckOrders,
    };

    fetch(instaxchangeAdmin.ajaxUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: new URLSearchParams(requestData),
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
      })
      .then((data) => {
        if (data.success) {
          const checked = data.data.checked;
          const updated = data.data.updated;
          let html = "<p>✅ Check completed successfully!</p>";
          html += `<p>📊 Checked: ${checked} orders</p>`;
          html += `<p>🔧 Updated: ${updated} stuck orders</p>`;

          if (updated > 0) {
            html +=
              "<p style='color: #28a745;'>✨ Successfully fixed stuck orders!</p>";
          } else if (checked > 0) {
            html +=
              "<p style='color: #6c757d;'>ℹ️ No stuck orders found to fix.</p>";
          } else {
            html +=
              "<p style='color: #6c757d;'>ℹ️ No pending orders with completed payments found.</p>";
          }

          resultDiv.innerHTML = html;
        } else {
          resultDiv.innerHTML =
            "<p>❌ Check failed: " + (data.data || "Unknown error") + "</p>";
        }
      })
      .catch((error) => {
        console.error("InstaxChange: Stuck orders check error:", error);
        resultDiv.innerHTML = "<p>❌ Check error: " + error.message + "</p>";
      })
      .finally(() => {
        button.disabled = false;
        button.textContent = "Check & Fix Stuck Orders";
      });
  };

  // Initialize admin settings enhancements
  $(document).ready(function () {
    console.log("InstaxChange: Initializing admin settings");

    // Ensure cryptocurrency section is visible
    ensureCryptocurrencyVisible();

    // Add visual enhancements to payment method groups
    enhancePaymentMethodDisplay();

    // Check for missing sections and add warnings
    checkMissingSections();
  });

  /**
   * Ensure cryptocurrency options are visible
   */
  function ensureCryptocurrencyVisible() {
    // Find cryptocurrency related fields
    const cryptoCheckbox = $('input[name*="enable_crypto"]');
    const cryptoSelect = $('select[name*="default_crypto"]');

    if (cryptoCheckbox.length === 0) {
      console.warn("InstaxChange: Cryptocurrency checkbox not found in DOM");
      // Add a temporary notice
      addMissingFieldNotice();
    } else {
      console.log("InstaxChange: Cryptocurrency options found and visible");
      
      // Highlight the crypto section
      cryptoCheckbox.closest('tr').addClass('wc-instaxchange-crypto-section');
      cryptoSelect.closest('tr').addClass('wc-instaxchange-crypto-section');
    }
  }

  /**
   * Add visual enhancements to payment method display
   */
  function enhancePaymentMethodDisplay() {
    // Group payment method sections
    const paymentMethodsTitle = $('th:contains("Payment Methods")').closest('tr');
    
    if (paymentMethodsTitle.length) {
      paymentMethodsTitle.after('<tr><td colspan="2"><div class="wc-instaxchange-method-group"><h4>🏦 Traditional Payment Methods</h4></div></td></tr>');
      
      // Find and group traditional methods
      const traditionalMethods = [
        'enable_traditional_methods',
        'enable_card',
        'enable_apple_pay',
        'enable_google_pay'
      ];
      
      traditionalMethods.forEach(method => {
        $(`[name*="${method}"]`).closest('tr').addClass('wc-traditional-methods');
      });
      
      // Add regional methods group
      $('[name*="enable_regional_methods"]').closest('tr').before('<tr><td colspan="2"><div class="wc-instaxchange-method-group"><h4>🌍 Regional Payment Methods</h4></div></td></tr>');
      
      // Add order management group
      $('[name*="enable_order_management"]').closest('tr').before('<tr><td colspan="2"><div class="wc-instaxchange-method-group"><h4>⚙️ Order Status Management</h4></div></td></tr>');
      
      // Add cryptocurrency methods group  
      $('[name*="enable_crypto"]').closest('tr').before('<tr><td colspan="2"><div class="wc-instaxchange-method-group"><h4>₿ Cryptocurrency Payments</h4></div></td></tr>');
    }
  }

  /**
   * Check for missing sections and add warnings
   */
  function checkMissingSections() {
    const requiredFields = [
      { name: 'enable_crypto', label: 'Cryptocurrency Payments' },
      { name: 'default_crypto', label: 'Default Cryptocurrency' }
    ];
    
    let missingFields = [];
    
    requiredFields.forEach(field => {
      const element = $(`[name*="${field.name}"]`);
      if (element.length === 0) {
        missingFields.push(field.label);
      }
    });
    
    if (missingFields.length > 0) {
      console.warn("InstaxChange: Missing payment method fields:", missingFields);
      addMissingFieldsWarning(missingFields);
    }
  }

  /**
   * Add notice for missing cryptocurrency fields
   */
  function addMissingFieldNotice() {
    const notice = $('<div class="notice notice-warning"><p><strong>InstaxChange:</strong> Cryptocurrency payment options are not visible. Please check your gateway configuration.</p></div>');
    $('.woocommerce-settings-title').after(notice);
  }

  /**
   * Add warning for missing fields
   */
  function addMissingFieldsWarning(fields) {
    const fieldsList = fields.join(', ');
    const warning = $(`<div class="notice notice-error"><p><strong>InstaxChange Configuration Issue:</strong> Missing payment method fields: ${fieldsList}. Please check your plugin installation.</p></div>`);
    $('.woocommerce-settings-title').after(warning);
  }

})(jQuery);
