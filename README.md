# WooCommerce InstaxChange Gateway

Accept cryptocurrency payments in your WooCommerce store using the InstaxChange payment gateway.

## 🚀 Features

- **Multiple Cryptocurrencies**: Accept USDC, Bitcoin, Ethereum, Litecoin, and more
- **Secure Integration**: Built-in webhook verification and secure API communication
- **HPOS Compatible**: Fully compatible with WooCommerce's High-Performance Order Storage
- **Mobile Responsive**: Works perfectly on all devices
- **Real-time Updates**: Automatic order status updates via webhooks
- **Easy Setup**: Simple configuration with InstaxChange dashboard
- **Debug Tools**: Built-in debugging and logging for troubleshooting

## 📋 Requirements

- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- SSL certificate (HTTPS) for webhook endpoints
- InstaxChange merchant account

## 🔧 Installation

### Method 1: Upload Plugin Files

1. **Download** all plugin files and create this folder structure:

   ```
   wc-instaxchange/
   ├── wc-instaxchange.php
   ├── includes/
   │   ├── class-gateway.php
   │   ├── class-admin.php
   │   └── class-webhook.php
   ├── assets/
   │   ├── style.css
   │   ├── admin-style.css
   │   └── icon.png (24x24px InstaxChange logo)
   └── README.md
   ```

2. **Zip the folder** and name it `wc-instaxchange.zip`

3. **Upload to WordPress**:
   - Go to **Plugins → Add New → Upload Plugin**
   - Choose your zip file and click **Install Now**
   - Click **Activate Plugin**

### Method 2: FTP Upload

1. **Upload** the `wc-instaxchange` folder to `/wp-content/plugins/`
2. **Go to WordPress Admin → Plugins**
3. **Find "WooCommerce InstaxChange Gateway"** and click **Activate**

## ⚙️ Configuration

### Step 1: InstaxChange Account Setup

1. **Register** at [InstaxChange Dashboard](https://instaxchange.com)
2. **Complete account verification**
3. **Navigate to Settings/API** section
4. **Copy your Account Reference ID**
5. **Generate a webhook secret key**

### Step 2: WordPress Configuration

1. **Go to WooCommerce → Settings → Payments**
2. **Find "InstaxChange"** and click **Set up**
3. **Check "Enable InstaxChange Gateway"**
4. **Fill in required settings**:

| Setting                      | Description                         | Required |
| ---------------------------- | ----------------------------------- | -------- |
| **Account Reference ID**     | From InstaxChange dashboard         | ✅ Yes   |
| **Webhook Secret**           | Generated in InstaxChange dashboard | ✅ Yes   |
| **Receiving Wallet Address** | Your crypto wallet address          | ✅ Yes   |
| **Default Cryptocurrency**   | USDC recommended                    | ✅ Yes   |
| **Title**                    | "Cryptocurrency Payment"            | No       |
| **Description**              | Customer-facing description         | No       |

5. **Click "Save changes"**

### Step 3: Webhook Configuration

1. **In InstaxChange dashboard**, set webhook URL to:
   ```
   https://yoursite.com/wc-api/wc_instaxchange_gateway
   ```
2. **Set the webhook secret** (same as in WordPress)
3. **Test webhook delivery**

## 🏗️ Supported Cryptocurrencies & Addresses

| Cryptocurrency      | Address Format           | Example         | Compatible Wallet          |
| ------------------- | ------------------------ | --------------- | -------------------------- |
| **USDC (Ethereum)** | `0x...`                  | `0x742d35Cc...` | MetaMask, Coinbase         |
| **USDC (Polygon)**  | `0x...`                  | `0x742d35Cc...` | MetaMask, Coinbase         |
| **Ethereum (ETH)**  | `0x...`                  | `0x742d35Cc...` | MetaMask, Coinbase         |
| **Bitcoin (BTC)**   | `1...`, `3...`, `bc1...` | `bc1qxy2kgd...` | Electrum, Hardware wallets |
| **Litecoin (LTC)**  | `L...`, `M...`           | `LTC4fYzne...`  | Litecoin Core              |

### 💡 Recommendation: Use USDC

**Why USDC is recommended:**

- ✅ Lower minimum amounts ($1-10 vs $100 for Bitcoin)
- ✅ Works with Ethereum addresses (most common)
- ✅ Stable value (pegged to USD)
- ✅ Faster transactions and lower fees

## 🔄 Payment Flow

1. **Customer** selects "Cryptocurrency Payment" at checkout
2. **Plugin** creates payment session with InstaxChange API
3. **Customer** is redirected to InstaxChange payment interface
4. **Customer** pays with credit card/bank transfer
5. **InstaxChange** converts to cryptocurrency and sends to your wallet
6. **Webhook** notifies your store of payment completion
7. **Order** is automatically marked as paid

## 🐛 Troubleshooting

### Common Issues

#### Payment Method Not Showing

1. **Check gateway is enabled**: WooCommerce → Settings → Payments → InstaxChange
2. **Verify required fields**: Account ID, Webhook Secret, Wallet Address
3. **Use debug force enable**: Check "Debug: Force Enable" temporarily
4. **Check browser console**: Look for JavaScript errors (F12)

#### API Errors

- **"Invalid address"**: Ensure wallet address matches cryptocurrency type
- **"Minimum amount"**: Bitcoin requires $100+, use USDC for smaller amounts
- **"Invalid account"**: Verify Account Reference ID from InstaxChange dashboard
- **"Authentication failed"**: Check Account Reference ID is correct

#### Webhook Issues

1. **Verify webhook URL**: Should be `https://yoursite.com/wc-api/wc_instaxchange_gateway`
2. **Check webhook secret**: Must match between WordPress and InstaxChange
3. **SSL certificate**: Webhook endpoints require HTTPS
4. **Server firewall**: Ensure InstaxChange can reach your webhook URL

### Debug Mode

**Enable WordPress debugging** in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

**Check logs** in `/wp-content/debug.log` for detailed error information.

**Use debug features**:

- Enable "Debug: Force Enable" in payment settings
- Check browser console for frontend issues
- Review webhook delivery in InstaxChange dashboard

### Testing

**Test Mode Configuration:**

1. **Enable "Test Mode"** in plugin settings
2. **Use test wallet address** if provided by InstaxChange
3. **Place small test orders** (minimum amounts apply)
4. **Verify webhook delivery** in InstaxChange dashboard

## 🔒 Security

### Webhook Security

- All webhooks are verified with MD5 signature
- Webhook secret must match between platforms
- HTTPS required for all webhook endpoints

### Data Protection

- No sensitive payment data stored locally
- All payments processed securely by InstaxChange
- Order data encrypted in transit and at rest

## 🎛️ Admin Features

### Order Management

- **Payment details** displayed in order admin
- **Transaction IDs** and blockchain hashes stored
- **Real-time status updates** via webhooks
- **Detailed payment logs** for troubleshooting

### Dashboard Widget

- **Payment statistics** overview
- **Recent transactions** list
- **Revenue tracking** by cryptocurrency

## 📱 Mobile Support

The plugin is fully responsive and works on:

- ✅ Desktop browsers
- ✅ Mobile browsers (iOS Safari, Android Chrome)
- ✅ Tablet devices
- ✅ Progressive Web Apps (PWA)

## 🔄 Updates

### Automatic Updates

The plugin checks for updates automatically. Update notifications appear in WordPress admin.

### Manual Updates

1. **Download** latest plugin files
2. **Deactivate** current plugin
3. **Upload** new files
4. **Reactivate** plugin

### Changelog

- **v1.0.2**: HPOS compatibility, enhanced error handling, improved UI
- **v1.0.1**: Bug fixes, webhook improvements
- **v1.0.0**: Initial release

## 🆘 Support

### Documentation

- [InstaxChange API Documentation](https://instaxchange.com/docs)
- [WooCommerce Developer Resources](https://woocommerce.com/developers/)

### Getting Help

1. **Check this documentation** first
2. **Enable debug mode** and check logs
3. **Test with default theme** and minimal plugins
4. **Contact InstaxChange support** for API issues
5. **Submit GitHub issue** for plugin-specific problems

### Self-Diagnosis

**Quick health check:**

```
✅ WooCommerce active and updated
✅ SSL certificate installed
✅ Account Reference ID configured
✅ Webhook URL set in InstaxChange dashboard
✅ Receiving wallet address valid for chosen cryptocurrency
✅ Test mode enabled for testing
```

## 📄 License

This plugin is licensed under the GPL v2 or later.

## 🤝 Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Submit a pull request with detailed description

---

**Made with ❤️ for the WooCommerce community**
