# WooCommerce Order Tracking

The **WooCommerce Order Tracking** plugin allows you to send order data from your WooCommerce store to another WooCommerce site using the **WooCommerce Tracking Receiver** plugin. It sends tracking information via a webhook whenever an order is placed.

## Features

- Sends order tracking data to a specified **WooCommerce Tracking Receiver** site.
- Automatically sends order data whenever a new order is placed.
- Requires WooCommerce to be installed and activated.
- Simple configuration with Webhook URL and API Key.

## Requirements

- **WooCommerce** is required.
- **WooCommerce Tracking Receiver** plugin must be installed on the site where you want to receive the order data.

## Installation

### 1. Install the Plugin
- Download and install the **WooCommerce Order Tracking** plugin on your WooCommerce site.
- Navigate to the **Plugins** section in WordPress.
- Click **Add New** and upload the plugin zip file.
- After installation, click **Activate**.

### 2. Configure Webhook URL and API Key
- Navigate to **WooCommerce > Order Tracking** after activating the plugin.
- You will need to provide the **Webhook URL** and **API Key** from the **WooCommerce Tracking Receiver** plugin.
  
  To get the Webhook URL and API Key:
  - Go to the **WooCommerce Tracking Receiver** site.
  - Navigate to **WP Admin > Order Tracking > Settings**.
  - Copy the **Webhook URL** and **API Key** and paste them into the **WooCommerce Order Tracking** settings page on your current site.

## How to Use

1. **Set Up Webhook URL and API Key:**
   - Go to **WooCommerce > Order Tracking** and enter the **Webhook URL** and **API Key** from your **WooCommerce Tracking Receiver** plugin.
   
2. **Send Order Data to the Receiver:**
   - After the configuration, every time a new order is placed on your WooCommerce site, the order data will be sent automatically to the specified **WooCommerce Tracking Receiver** site using the webhook.

3. **Webhook Transmission:**
   - The plugin ensures that the order details, including tracking information, are transmitted to the **WooCommerce Tracking Receiver** whenever an order is placed.

## Configuration

### Webhook URL and API Key
- Enter the **Webhook URL** and **API Key** from your **WooCommerce Tracking Receiver** site to link your store to the tracking receiver.
  
### Settings Page
- To access the settings page, navigate to **WooCommerce > Order Tracking**. Here you can enter the necessary details for integration with the **WooCommerce Tracking Receiver**.

## FAQs

### How do I configure the plugin?
- After activation, go to **WooCommerce > Order Tracking** and input the **Webhook URL** and **API Key** you obtained from the **WooCommerce Tracking Receiver** plugin.

### Is WooCommerce required for this plugin to work?
- Yes, **WooCommerce** is a requirement as the plugin sends order data from WooCommerce orders.

### How does the webhook work?
- The webhook sends order data to the **WooCommerce Tracking Receiver** site every time a new order is placed on your store.

### Do I need to install the "WooCommerce Tracking Receiver" plugin?
- Yes, the **WooCommerce Tracking Receiver** plugin must be installed and configured on the receiving site to process the order data.

---

*This plugin is developed by Chetan Vaghela.*

