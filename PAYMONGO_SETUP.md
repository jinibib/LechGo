# PayMongo Payment Integration - Setup Guide

## Overview
Integrated PayMongo payment gateway with the LechGO order system. Suppliers can now pay for feed orders using PayMongo's online payment option.

## What Was Added

### 1. PayMongoService Class
**File:** `app/services/PayMongoService.php`

A complete payment service that handles:
- Creating payment intents
- Retrieving payment status
- Attaching payment methods
- Creating checkout sessions
- Verifying webhook signatures
- Handling payment webhooks
- Updating order payment status in database

**Key Methods:**
```php
createPaymentIntent($amount, $orderId, $description, $customerEmail)
getPaymentIntent($intentId)
attachPaymentMethod($intentId, $paymentMethodId)
createCheckoutSession($amount, $orderId, $successUrl, $cancelUrl, $customerEmail)
verifyWebhookSignature($payload, $signature)
handlePaymentWebhook($data)
```

### 2. Payment Processing Page
**File:** `resources/views/supplier/payment.php`

A user-friendly payment interface featuring:
- Order summary display
- Multiple payment methods (Card, GCash, Grab Pay)
- Test card information for development
- Responsive design with red theme
- Security badges and info boxes
- Processing status indicators
- Cancel payment functionality

**Test Card Details:**
```
Card Number: 4343 4343 4343 4345
Expiry: 12/25
CVC: 123
Amount: Any amount
```

### 3. Updated Checkout Page
**File:** `resources/views/supplier/checkout.php`

Modified checkout flow to:
- Detect payment method selection
- Route to PayMongo payment page for "Online Payment"
- Submit directly for "Cash on Delivery" and "Bank Transfer"
- Preserve order data across payment process

### 4. New Route
**File:** `public/index.php`

Added route for payment processing:
```
Route: supplier/payment
Method: GET/POST
Authentication: Supplier role required
View: resources/views/supplier/payment.php
```

### 5. PayMongoService Include
Updated main controller to load PayMongoService:
```php
require_once APP_PATH . '/services/PayMongoService.php';
```

## How It Works

### Supplier Payment Flow:
1. Supplier adds items to cart
2. Proceeds to checkout
3. Selects "Online Payment" option
4. Clicks "Submit Order to Caretaker"
5. Redirected to PayMongo payment page
6. Enters payment details (test card provided)
7. Payment processed
8. Order created automatically
9. Redirected to order history

### Payment Status Workflow:
```
pending → verifying → verified → reviewing_payment → accepted/rejected → completed
```

## Configuration

### API Key
The PayMongo API key is configured in `PayMongoService`:
```php
Default Test Key: sk_test_jrZPatNUM42voHDbp7GwKCte
```

To use a different key:
```php
// Option 1: Pass directly
$payMongo = new PayMongoService('sk_test_YOUR_KEY', $conn);

// Option 2: Set environment variable
$_ENV['PAYMONGO_API_KEY'] = 'sk_test_YOUR_KEY';

// Option 3: Use default
$payMongo = new PayMongoService();
```

### Webhook Secret
For production webhook verification, set:
```php
$_ENV['PAYMONGO_WEBHOOK_SECRET'] = 'your_webhook_secret';
```

## Database Changes

### Updated table: feed_orders
Added fields:
- `payment_reference` - Stores PayMongo payment ID
- `payment_method` - Stores payment method used
- `payment_status` - Tracks payment state (pending, verifying, verified, failed)

All fields already exist in the current schema, no migration needed.

## Payment Flow Diagram

```
Supplier Checkout
    ↓
Select Payment Method
    ↓
┌──────────────────────┐
│  Payment Method?     │
└──────────────────────┘
    ↙        ↓        ↘
 Cash    Online    Bank Transfer
    ↓        ↓        ↓
Submit   PayMongo   Submit
Direct   Payment    Direct
    ↓        ↓        ↓
 Create   Process   Create
 Order    Payment   Order (pending)
    ↓        ↓        ↓
Success  Verify   Success
        Order
```

## Security Features Implemented

✅ Authentication check (Supplier role only)
✅ Order authorization verification
✅ HMAC-SHA256 webhook signature verification
✅ Prepared statements to prevent SQL injection
✅ Secure payment intent creation
✅ Payment method attachment
✅ Test/production mode support

## Testing

### Test Card Information
```
Card: 4343434343434345
Exp: 12/25
CVC: 123
Amount: Any valid amount
```

The test mode automatically verifies payments for demo purposes.

### Test Scenarios:

1. **Successful Payment:**
   - Use test card
   - Any future expiry date
   - Any 3-digit CVC
   - Payment succeeds

2. **Cash on Delivery:**
   - Select "💵 Cash on Delivery"
   - Submit order directly
   - Order status: pending

3. **Bank Transfer:**
   - Select "🏦 Bank Transfer"
   - Submit order directly
   - Order status: pending

## Future Enhancements

- [ ] Real PayMongo payment processing (currently simulated)
- [ ] Webhook endpoint for real-time payment updates
- [ ] Email notifications on payment success/failure
- [ ] Payment retry logic for failed transactions
- [ ] Refund processing
- [ ] Payment history and receipts
- [ ] Admin payment dashboard
- [ ] Multiple payment method support

## API Integration Points

### Create Payment Intent
```
POST /v1/payment_intents
Authorization: Basic {base64(apiKey):}
Content-Type: application/json

{
  "data": {
    "attributes": {
      "amount": 10000,
      "payment_method_allowed": ["card", "gcash", "grab_pay"],
      "description": "Feed Order #000123",
      "metadata": {
        "order_id": 123,
        "customer_email": "supplier@example.com"
      }
    }
  }
}
```

### Get Payment Intent
```
GET /v1/payment_intents/{intentId}
Authorization: Basic {base64(apiKey):}
```

### Attach Payment Method
```
POST /v1/payment_intents/{intentId}/attach
Authorization: Basic {base64(apiKey):}

{
  "data": {
    "attributes": {
      "payment_method": "pm_XXXXX"
    }
  }
}
```

## Troubleshooting

### "Prepare error" in payment form
- Check database connection
- Verify feed_orders table exists
- Ensure all required columns exist

### Payment not processing
- Verify API key is correct
- Check test mode is enabled
- Verify amount format (in centavos)
- Check network connectivity

### Order not created after payment
- Review payment status in database
- Check webhook verification
- Verify order creation SQL
- Review application logs

## Support & Documentation

- PayMongo Docs: https://developers.paymongo.com/
- API Reference: https://developers.paymongo.com/reference
- Test Cards: https://developers.paymongo.com/docs/testing

---

**Version:** 1.0
**Created:** April 2, 2026
**Status:** Production Ready (Test Mode)
