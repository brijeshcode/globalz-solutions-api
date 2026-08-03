# Project Guidelines

## Adding a New Feature Flag

Follow these steps every time you add a new feature to the system:

1. Add the feature entry (key, name, description) to `DEFAULT_FEATURES` in `app/Http/Controllers/Api/Landlord/FeatureController.php`
2. Call the seed endpoint on the live system to insert the new feature into the landlord database
3. Add `middleware('feature:your_key')` to the relevant routes in `routes/api.php`
4. Use `FeatureHelper::isEnabled('your_key')` in the business logic where the feature gate is needed
5. If the feature will be checked frequently, add a convenience method to `app/Helpers/FeatureHelper.php` (e.g., `isYourFeature()`)
6. Enable the feature for the specific tenant(s) via the landlord admin panel
