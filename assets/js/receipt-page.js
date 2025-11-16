/**
 * InstaxChange Receipt Page JavaScript
 * Handles payment method switching, status checking, and iframe management
 */

(function ($) {
  "use strict";

  // Global variables
  let currentSessionId = instaxchangeData.currentSessionId;
  let currentMethod = "card";
  let isCreatingSession = false;

  /**
   * Payment method switching
   */
  window.switchPaymentMethod = function (method) {
    if (isCreatingSession) {
      console.log("InstaxChange: Session creation in progress...");
      return;
    }

    console.log("InstaxChange: Switching to method:", method);
    currentMethod = method;

    // Update button states
    document.querySelectorAll(".method-btn").forEach((btn) => {
      btn.classList.remove("active");
    });

    // Find and activate the correct button
    const targetBtn = document.querySelector(
      `.method-btn[data-method="${method}"]`
    );

    if (targetBtn) {
      targetBtn.classList.add("active");
    } else {
      console.warn("InstaxChange: Target button not found for method:", method);
    }

    showIframeLoading();
    createSessionWithMethod(method);
  };

  /**
   * Create session with payment method
   */
  function createSessionWithMethod(method) {
    if (isCreatingSession) return;

    isCreatingSession = true;
    const methodDisplay = getMethodDisplayName(method);
    console.log("InstaxChange: Creating session with method:", methodDisplay);

    const statusResult = document.getElementById("status-result");
    if (statusResult) {
      statusResult.innerHTML = `<div class="status-creating">🔄 Creating payment session with ${methodDisplay}...</div>`;
      statusResult.className = "status-result creating";
    }

    // Prepare request data
    const requestData = {
      action: "create_instaxchange_session",
      order_id: instaxchangeData.orderId,
      payment_method: method,
      nonce: instaxchangeData.nonces.createSession,
    };

    fetch(instaxchangeData.ajaxUrl, {
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
        isCreatingSession = false;
        console.log("InstaxChange: Session response:", data);

        if (data.success) {
          currentSessionId = data.data.session_id;
          const actualMethod = data.data.fallback_used
            ? data.data.payment_method
            : method;
          const actualMethodDisplay = getMethodDisplayName(actualMethod);

          loadIframeWithSession(currentSessionId, actualMethod);

          if (statusResult) {
            if (data.data.fallback_used) {
              // Show warning popup for fallback
              showFallbackWarningPopup(methodDisplay, actualMethodDisplay);
              statusResult.innerHTML = `<div class="status-success">✅ Payment session ready with ${actualMethodDisplay}</div>`;
              statusResult.className = "status-result success";
            } else {
              statusResult.innerHTML = `<div class="status-success">✅ Payment session ready with ${methodDisplay}</div>`;
              statusResult.className = "status-result success";
            }

            setTimeout(() => {
              statusResult.innerHTML = "";
              statusResult.className = "status-result";
            }, 3000);
          }

          // Update current method to reflect what was actually loaded
          currentMethod = actualMethod;
        } else {
          console.error("InstaxChange: Session creation failed:", data.data);

          if (statusResult) {
            // Check if it's a configuration error
            const errorMessage = data.data;
            if (
              errorMessage &&
              errorMessage.includes("configuration incomplete")
            ) {
              statusResult.innerHTML = `<div class="status-error">❌ Payment gateway not configured. Please contact store administrator.</div>`;
            } else {
              statusResult.innerHTML = `<div class="status-error">❌ Failed to create session with ${methodDisplay}. ${
                errorMessage || "Please try again."
              }</div>`;
            }
            statusResult.className = "status-result error";
          }

          // Don't fallback to original session if configuration is missing
          if (!data.data || !data.data.includes("configuration incomplete")) {
            // Fallback to original session only for network/other errors
            loadIframeWithSession(instaxchangeData.currentSessionId, "card");
          }
        }
      })
      .catch((error) => {
        isCreatingSession = false;
        console.error("InstaxChange: Network error:", error);

        if (statusResult) {
          // Check if it's a configuration error
          const errorMessage = error.message || "";
          if (errorMessage.includes("configuration incomplete")) {
            statusResult.innerHTML = `<div class="status-error">❌ Payment gateway not configured. Please contact store administrator.</div>`;
            statusResult.className = "status-result error";
          } else if (
            errorMessage.includes("400") ||
            errorMessage.includes("Bad Request")
          ) {
            // InstaxChange API doesn't support this payment method
            // Show warning popup for unsupported method
            showFallbackWarningPopup(methodDisplay, "Credit/Debit Cards");

            // Show creating message for fallback
            if (statusResult) {
              statusResult.innerHTML = `<div class="status-creating">🔄 Creating payment session with Credit/Debit Cards...</div>`;
              statusResult.className = "status-result creating";
            }

            // Fallback to default card payment
            setTimeout(() => {
              loadIframeWithSession(instaxchangeData.currentSessionId, "card");
              // Reset button states to show card as active
              document.querySelectorAll(".method-btn").forEach((btn) => {
                btn.classList.remove("active");
              });
              const cardBtn = document.querySelector(
                '.method-btn[data-method="card"]'
              );
              if (cardBtn) {
                cardBtn.classList.add("active");
              }

              // Clear the creating message after fallback
              if (statusResult) {
                setTimeout(() => {
                  statusResult.innerHTML = `<div class="status-success">✅ Payment session ready with Credit/Debit Cards</div>`;
                  statusResult.className = "status-result success";
                  setTimeout(() => {
                    statusResult.innerHTML = "";
                    statusResult.className = "status-result";
                  }, 3000);
                }, 1000);
              }
            }, 2000);
          } else {
            statusResult.innerHTML = `<div class="status-error">❌ Network error: ${error.message}. Using default session.</div>`;
            statusResult.className = "status-result error";
            // Only fallback for network errors, not configuration errors
            loadIframeWithSession(instaxchangeData.currentSessionId, "card");
          }
        } else {
          // Fallback for network errors
          loadIframeWithSession(instaxchangeData.currentSessionId, "card");
        }
      });
  }

  /**
   * Load iframe with better URL construction
   */
  function loadIframeWithSession(sessionId, method, crypto = null) {
    const iframe = document.getElementById("instaxchange-payment-iframe");
    const overlay = document.querySelector(".iframe-loading-overlay");

    if (!iframe) {
      console.error("InstaxChange: Iframe not found");
      return;
    }

    // Use the correct InstaxChange embed URL as per their documentation
    let iframeUrl = `https://instaxchange.com/embed/${sessionId}`;

    // Add minimal parameters - the session already contains all necessary payment configuration
    // InstaxChange iframe will show appropriate interface based on session data
    const urlParams = new URLSearchParams();
    urlParams.append("t", Date.now());

    iframeUrl += "?" + urlParams.toString();

    console.log("InstaxChange: Loading iframe with URL:", iframeUrl);

    // Show loading
    if (overlay) {
      overlay.style.display = "flex";
      overlay.style.opacity = "1";
    }

    // Load iframe
    iframe.src = iframeUrl;

    // Handle iframe events
    let iframeLoadTimeout;

    iframe.onload = function () {
      console.log("InstaxChange: Iframe loaded successfully");
      clearTimeout(iframeLoadTimeout); // Clear the timeout since iframe loaded

      setTimeout(() => {
        if (overlay) {
          overlay.style.opacity = "0";
          setTimeout(() => (overlay.style.display = "none"), 300);
        }
      }, 1000);

      // The iframe will automatically show the correct payment interface
      // based on the session configuration from the API
      console.log("InstaxChange: Iframe loaded with session:", sessionId);
    };

    // Set a timeout for iframe loading
    iframeLoadTimeout = setTimeout(() => {
      console.log("InstaxChange: Iframe load timeout");
      hideIframeLoading();
    }, 10000); // 10 second timeout

    iframe.onerror = function () {
      console.error("InstaxChange: Iframe failed to load");
      hideIframeLoading();
    };
  }

  /**
   * Helper functions
   */
  function showIframeLoading() {
    const overlay = document.querySelector(".iframe-loading-overlay");
    if (overlay) {
      overlay.style.display = "flex";
      overlay.style.opacity = "1";
    }
  }

  function hideIframeLoading() {
    const overlay = document.querySelector(".iframe-loading-overlay");
    if (overlay) {
      overlay.style.opacity = "0";
      setTimeout(() => (overlay.style.display = "none"), 300);
    }
  }

  function getMethodDisplayName(method) {
    const names = {
      card: "Credit/Debit Cards",
      "apple-pay": "Apple Pay",
      "google-pay": "Google Pay",
      ideal: "iDEAL",
      bancontact: "Bancontact",
      interac: "Interac",
      pix: "PIX",
      sepa: "SEPA",
      poli: "POLi",
      blik: "BLIK",
      "usdc-polygon": "USDC Polygon",
      usdc_polygon: "USDC Polygon",
    };
    return names[method] || method;
  }

  /**
   * Payment status checking
   */
  window.checkPaymentStatus = function () {
    const resultDiv = document.getElementById("status-result");
    const checkBtn = document.querySelector(
      ".status-check-container .instax-button"
    );

    if (checkBtn) {
      checkBtn.innerHTML =
        '<span class="dashicons dashicons-update spinning"></span> Checking...';
      checkBtn.disabled = true;
    }

    if (resultDiv) {
      resultDiv.innerHTML =
        '<div class="status-checking">🔄 Checking payment status...</div>';
      resultDiv.className = "status-result checking";
    }

    fetch(instaxchangeData.ajaxUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: new URLSearchParams({
        action: "check_instaxchange_status",
        order_id: instaxchangeData.orderId,
        nonce: instaxchangeData.nonces.checkStatus,
      }),
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
      })
      .then((data) => {
        if (data.success) {
          const status = data.data.status;
          const orderStatus = data.data.order_status;
          let statusIcon = "⏳";
          let statusClass = "pending";
          let statusMessage = data.data.status_text || status;

          console.log("InstaxChange: Status check result:", data.data);

          switch (status) {
            case "completed":
            case "success":
              statusIcon = "✅";
              statusClass = "success";
              statusMessage = "Payment Completed Successfully!";

              // Show success message and redirect
              if (resultDiv) {
                resultDiv.innerHTML = `<div class="status-update ${statusClass}">${statusIcon} <strong>${statusMessage}</strong><br><small>Redirecting to order confirmation...</small></div>`;
                resultDiv.className = `status-result ${statusClass}`;
              }

              // Redirect to order received page
              setTimeout(() => {
                console.log("InstaxChange: Redirecting to order received page");
                window.location.href = instaxchangeData.orderReceivedUrl;
              }, 3000);
              break;

            case "failed":
            case "error":
              statusIcon = "❌";
              statusClass = "failed";
              statusMessage = "Payment Failed";
              break;

            case "processing":
              statusIcon = "⏳";
              statusClass = "processing";
              statusMessage = "Payment Processing";
              break;

            case "pending":
              statusIcon = "⏳";
              statusClass = "pending";
              statusMessage = "Awaiting Payment";
              break;

            default:
              statusIcon = "❓";
              statusClass = "unknown";
              statusMessage = `Status: ${status} (Order: ${orderStatus})`;
          }

          if (resultDiv && status !== "completed") {
            resultDiv.innerHTML = `<div class="status-update ${statusClass}">${statusIcon} <strong>${statusMessage}</strong></div>`;
            resultDiv.className = `status-result ${statusClass}`;
          }
        } else {
          if (resultDiv) {
            resultDiv.innerHTML =
              '<div class="status-error">❌ Error checking status. Please try again.</div>';
            resultDiv.className = "status-result error";
          }
        }
      })
      .catch((error) => {
        console.error("Status check error:", error);
        if (resultDiv) {
          resultDiv.innerHTML = `<div class="status-error">❌ Network error: ${error.message}. Please try again.</div>`;
          resultDiv.className = "status-result error";
        }
      })
      .finally(() => {
        if (checkBtn) {
          checkBtn.innerHTML =
            '<span class="dashicons dashicons-update"></span> Check Payment Status';
          checkBtn.disabled = false;
        }
      });
  };

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

  /**
   * Toggle order details
   */
  window.toggleOrderDetails = function () {
    const content = document.querySelector(".order-details-content");
    const toggle = document.querySelector(".details-toggle");
    const icon = toggle.querySelector(".toggle-icon");
    const text = toggle.querySelector(".toggle-text");

    if (content.style.display === "none") {
      content.style.display = "block";
      icon.textContent = "▲";
      text.textContent = "Hide Order Details";
    } else {
      content.style.display = "none";
      icon.textContent = "▼";
      text.textContent = "View Order Details";
    }
  };

  /**
   * Initialize page
   */
  $(document).ready(function () {
    console.log("InstaxChange: Payment page loaded");

    // Load iframe with default method if session exists
    if (currentSessionId) {
      loadIframeWithSession(currentSessionId, "card");
    }

    // Auto-check payment status every 15 seconds for faster response
    let checkCount = 0;
    const maxChecks = 40;

    const autoCheck = setInterval(() => {
      if (checkCount >= maxChecks) {
        clearInterval(autoCheck);
        console.log(
          "InstaxChange: Auto-check limit reached, stopping automatic checks"
        );
        return;
      }
      checkCount++;

      // Silent status check
      fetch(instaxchangeData.ajaxUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
          action: "check_instaxchange_status",
          order_id: instaxchangeData.orderId,
          nonce: instaxchangeData.nonces.checkStatus,
        }),
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
          }
          return response.json();
        })
        .then((data) => {
          if (data.success) {
            const status = data.data.status;
            const orderStatus = data.data.order_status;

            console.log(
              "InstaxChange: Auto-check result - Status:",
              status,
              "Order Status:",
              orderStatus
            );

            if (status === "completed" || status === "success") {
              clearInterval(autoCheck);
              console.log(
                "InstaxChange: Payment completed, redirecting to order received page"
              );

              // Show success message briefly before redirect
              const statusResult = document.getElementById("status-result");
              if (statusResult) {
                statusResult.innerHTML =
                  '<div class="status-success">✅ Payment completed! Redirecting...</div>';
                statusResult.className = "status-result success";
              }

              setTimeout(() => {
                window.location.href = instaxchangeData.orderReceivedUrl;
              }, 2000);
            } else if (status === "failed" || status === "error") {
              clearInterval(autoCheck);
              console.log("InstaxChange: Payment failed, stopping auto-checks");
            }
          }
        })
        .catch((error) => {
          // Log error but don't show to user (silent fail)
          console.log(
            "InstaxChange: Auto-check error (silent):",
            error.message
          );

          // Stop checking if we get too many errors
          if (checkCount > 10) {
            console.log("InstaxChange: Too many auto-check errors, stopping");
            clearInterval(autoCheck);
          }
        });
    }, 15000); // Check every 15 seconds
  });

  /**
   * Listen for iframe messages
   */
  window.addEventListener("message", function (event) {
    if (event.origin.includes("instaxchange.com")) {
      console.log("InstaxChange: Message from iframe:", event.data);

      // Handle payment completion
      if (
        event.data.type === "payment_completed" ||
        event.data.status === "completed" ||
        event.data.payment_status === "completed" ||
        event.data.type === "payment_success"
      ) {
        console.log(
          "InstaxChange: Payment completed message received from iframe"
        );

        // Show success message
        const statusResult = document.getElementById("status-result");
        if (statusResult) {
          statusResult.innerHTML =
            '<div class="status-success">✅ Payment completed! Redirecting to order confirmation...</div>';
          statusResult.className = "status-result success";
        }

        // Force a status check to ensure order is updated
        setTimeout(() => {
          window.checkPaymentStatus();
        }, 1000);

        // Redirect to order received page
        setTimeout(() => {
          console.log(
            "InstaxChange: Redirecting to order received page from iframe message"
          );
          window.location.href = instaxchangeData.orderReceivedUrl;
        }, 3000);
      }

      // Handle payment failure
      if (
        event.data.type === "payment_failed" ||
        event.data.status === "failed" ||
        event.data.payment_status === "failed"
      ) {
        console.log(
          "InstaxChange: Payment failed message received from iframe"
        );

        const statusResult = document.getElementById("status-result");
        if (statusResult) {
          statusResult.innerHTML =
            '<div class="status-error">❌ Payment failed. Please try again or select a different payment method.</div>';
          statusResult.className = "status-result error";
        }
      }

      // Handle payment method changes
      if (event.data.type === "method_changed" && event.data.method) {
        console.log(
          "InstaxChange: Payment method changed in iframe to:",
          event.data.method
        );
        currentMethod = event.data.method;

        // Update button states
        document.querySelectorAll(".method-btn").forEach((btn) => {
          btn.classList.remove("active");
          if (btn.getAttribute("data-method") === event.data.method) {
            btn.classList.add("active");
          }
        });
      }

      // Handle any other payment status updates
      if (event.data.payment_status || event.data.status) {
        console.log(
          "InstaxChange: Payment status update from iframe:",
          event.data.payment_status || event.data.status
        );

        // Check if we should update the status display
        if (
          event.data.payment_status === "completed" ||
          event.data.status === "completed"
        ) {
          setTimeout(() => {
            window.checkPaymentStatus();
          }, 1000);
        }
      }
    }
  });

  /**
   * Show more cryptocurrency options
   */
  window.showMoreCryptoOptions = function () {
    const extendedOptions = document.querySelector('.crypto-methods-extended');
    const moreButton = document.querySelector('.method-btn-more');
    
    if (extendedOptions && moreButton) {
      if (extendedOptions.style.display === 'none') {
        // Show extended options
        extendedOptions.style.display = 'block';
        
        // Update the more button
        const icon = moreButton.querySelector('.method-icon');
        const name = moreButton.querySelector('.method-name');
        const desc = moreButton.querySelector('.method-desc');
        
        if (icon) icon.textContent = '⬆️';
        if (name) name.textContent = 'Less Crypto';
        if (desc) desc.textContent = 'Hide additional options';
        
        moreButton.onclick = hideCryptoOptions;
      }
    }
  };

  /**
   * Hide extended cryptocurrency options
   */
  window.hideCryptoOptions = function () {
    const extendedOptions = document.querySelector('.crypto-methods-extended');
    const moreButton = document.querySelector('.method-btn-more');
    
    if (extendedOptions && moreButton) {
      // Hide extended options
      extendedOptions.style.display = 'none';
      
      // Reset the more button
      const icon = moreButton.querySelector('.method-icon');
      const name = moreButton.querySelector('.method-name');
      const desc = moreButton.querySelector('.method-desc');
      
      if (icon) icon.textContent = '⚡';
      if (name) name.textContent = 'More Crypto';
      if (desc) desc.textContent = 'SOL, ADA, DOT & more';
      
      moreButton.onclick = showMoreCryptoOptions;
    }
  };

})(jQuery);
