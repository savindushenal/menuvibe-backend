# Payment Callback Order ID Fix

**Date:** January 14, 2026  
**Issue:** Payment callback receiving wrong order_id format causing 500 errors  
**Status:** ✅ FIXED (Deployed: commit ff85822)

---

## Problem Description

### Symptoms
- Payment callback returning 500 error
- Receiving `order_id=S73_68382637` instead of expected format
- Expected format: `USER_123_PLAN_456_1768382637`

### Root Cause
**Misunderstanding of Absterco API behavior:**

1. **What we send to Absterco:**
   - `order_reference: "USER_123_PLAN_456_1768382637"`
   
2. **What Absterco returns in callback:**
   - `order_id: "S73_68382637"` ← Their own generated ID
   - `session_id: "73"` ← Payment session identifier

3. **The mistake:**
   - We were trying to parse `order_id` (Absterco's ID)
   - But we should use `session_id` to verify and get back our `order_reference`

---

## Solution Implemented

### Changed Callback Flow

**Before (❌ Wrong):**
```php
// Tried to parse order_id directly
if (preg_match('/USER_(\d+)_PLAN_(\d+)_/', $orderId, $matches)) {
    // This failed because $orderId = "S73_68382637"
}
```

**After (✅ Correct):**
```php
// Step 1: Verify payment with Absterco using session_id
$verification = $this->paymentService->verifyPayment($sessionId);

// Step 2: Extract our order_reference from verification response
$orderReference = $verification['order_reference']; // "USER_123_PLAN_456_..."

// Step 3: Parse OUR order_reference
if (preg_match('/USER_(\d+)_PLAN_(\d+)_/', $orderReference, $matches)) {
    $userId = $matches[1];
    $planId = $matches[2];
}
```

### Code Changes

#### File: `app/Http/Controllers/SubscriptionPaymentController.php`

**Lines 178-230** - Updated `paymentCallback()` method:

1. **Removed dependency on order_id parameter**
   - Now only requires `session_id`
   - `order_id` is logged but not used for parsing

2. **Added verification step**
   - Calls `verifyPayment($sessionId)` to get full payment details
   - Extracts `order_reference` from verification response
   
3. **Parse order_reference instead of order_id**
   - Now parses our own `USER_PLAN` format
   - Properly extracts userId and planId

4. **Added debug logging**
   - Logs verification response for troubleshooting
   - Helps diagnose future payment issues

#### File: `app/Services/AbstercoPaymentService.php`

**Lines 97-106** - Added debug logging:
- Logs outgoing payment request (order_reference, amount, success_url)
- Logs payment link response (session_id, order_id, payment_url)
- Helps trace the payment flow

**Lines 147-183** - `verifyPayment()` method:
- Already returns `order_reference` field ✅
- Returns complete payment data including:
  - status
  - amount, currency
  - order_reference ← **Key field**
  - card_saved, saved_card_id
  - transaction_id
  - metadata

---

## Deployment Status

### Commits
1. **f7693b9** - Added deployment status endpoint and debug logging
2. **ff85822** - Fixed callback to verify payment and extract order_reference ✅

### Deployed To
- **Railway:** https://api.menuvire.com
- **Branch:** main
- **Status:** Deployed ✅

### Pending Actions
⏳ **Migration Required:** Run `php artisan migrate --force` on Railway

Migration: `2026_01_14_091515_add_business_profile_id_to_user_subscriptions_table`

See: `run_railway_migration.md` for instructions

---

## Testing

### Test Flow
1. User initiates subscription payment
2. Backend generates order_reference: `USER_123_PLAN_456_1768382637`
3. Absterco creates payment session: `session_id=73`
4. Absterco generates their order_id: `S73_68382637`
5. User completes payment
6. Absterco redirects to callback with:
   - `session_id=73`
   - `order_id=S73_68382637`
   - `amount=29.00`
   - `currency=LKR`
7. **Backend verifies payment:**
   ```php
   $verification = verifyPayment('73');
   // Returns: { order_reference: "USER_123_PLAN_456_1768382637" }
   ```
8. Backend parses order_reference to get user and plan
9. Subscription created successfully ✅

### Debug Endpoints

**Check deployment status:**
```
GET https://api.menuvire.com/api/deployment-status
```

Returns:
```json
{
  "status": "ok",
  "git_commit": "ff85822",
  "service_exists": true,
  "controller_exists": true,
  "order_ref_method": true
}
```

**Check logs:**
```
Railway Dashboard → Latest Deployment → View Logs
```

Look for:
```
[2026-01-14 10:30:15] INFO: Absterco Payment Request
{
  "order_reference": "USER_123_PLAN_456_1768382637",
  "amount": 29.00,
  "success_url": "https://staging.app.menuvire.com/..."
}

[2026-01-14 10:30:16] INFO: Absterco Payment Response
{
  "session_id": "73",
  "order_id": "S73_68382637",
  "payment_url": "https://..."
}

[2026-01-14 10:35:20] INFO: Payment verification result
{
  "session_id": "73",
  "verification": {
    "order_reference": "USER_123_PLAN_456_1768382637",
    "status": "completed"
  }
}
```

---

## Key Learnings

### Absterco Payment Gateway Behavior

1. **order_reference vs order_id**
   - `order_reference` = What you send (our format)
   - `order_id` = What Absterco generates (their format)
   - Callback returns `order_id`, NOT `order_reference`

2. **Callback Parameters**
   - `session_id` - Use this to verify payment
   - `order_id` - Absterco's ID (format: S{session}_{timestamp})
   - `amount` - Payment amount
   - `currency` - Payment currency

3. **Verification API**
   - Endpoint: `/api/v1/payment-links/{session_id}/verify`
   - Returns YOUR `order_reference` in response
   - This is how you get back your original reference

### Best Practices

✅ **DO:**
- Store session_id when creating payment
- Use session_id to verify payment status
- Extract order_reference from verification response
- Log all payment requests and responses

❌ **DON'T:**
- Try to parse Absterco's order_id
- Assume callback will return your order_reference directly
- Skip payment verification step

---

## Related Files

- `app/Http/Controllers/SubscriptionPaymentController.php` - Payment callback handler
- `app/Services/AbstercoPaymentService.php` - Payment gateway integration
- `config/payment.php` - Payment configuration
- `routes/api.php` - Added deployment status endpoint
- `PAYMENT_INTEGRATION_V2.md` - Full integration documentation
- `run_railway_migration.md` - Migration instructions

---

## Next Steps

1. ✅ Code deployed to Railway (commit ff85822)
2. ⏳ Run migration on Railway
3. ⏳ Test complete payment flow
4. ⏳ Monitor logs for any issues
5. ⏳ Update user-facing documentation

---

**Issue Resolved:** Payment callback now correctly verifies with Absterco and extracts order_reference instead of trying to parse order_id. This allows proper user and plan identification for subscription creation.
