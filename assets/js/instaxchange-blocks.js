(function () {
  "use strict";

  // Minimal blocks registration for InstaxChange
  if (
    typeof window.wc !== "undefined" &&
    typeof window.wc.wcBlocksRegistry !== "undefined"
  ) {
    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;

    const paymentMethodConfig = {
      name: "instaxchange",
      label: "Pay with InstaxChange",
      content:
        "Secure payments with cards, wallets, bank transfers, and cryptocurrency.",
      edit: "Secure payments with cards, wallets, bank transfers, and cryptocurrency.",
      canMakePayment: () => true,
      paymentMethodId: "instaxchange",
      supports: {
        features: ["products"],
      },
    };

    registerPaymentMethod(paymentMethodConfig);
  }
})();
