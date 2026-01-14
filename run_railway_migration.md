# Run Migration on Railway

To run the pending migration on Railway:

1. Go to Railway dashboard: https://railway.app
2. Select your `menuvibe-backend` project
3. Go to the **Deployments** tab
4. Click on the latest deployment
5. Click **"View Logs"** in the top right
6. Click **"Deploy"** button dropdown → **"Run Command"**
7. Enter command: `php artisan migrate --force`
8. Click **"Run"**

This will execute the migration:
```
2026_01_14_091515_add_business_profile_id_to_user_subscriptions_table
```

The migration adds:
- `business_profile_id` column to `user_subscriptions` table
- Foreign key constraint to `business_profiles` table
- Index on `business_profile_id` for performance

---

## Alternative: SSH into Railway (if enabled)

```bash
railway run php artisan migrate --force
```

## Verify Migration

After running, check with:
```bash
railway run php artisan migrate:status
```

Or use the test endpoint:
```
GET https://api.menuvire.com/api/deployment-status
```
