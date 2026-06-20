# MaklaPlace - WordPress Marketplace Plugin Specification

## Project Overview:
MaklaPlace is a WordPress plugin that transforms WordPress into a marketplace connecting customers with independent chefs.
The plugin should be modular, scalable, secure, and built using WordPress best practices. Every major feature should be developed as an independent module to simplify future maintenance and expansion.

## Plugin Architecture:
Use a modular architecture:
- Core
- Authentication
- Customers
- Chefs
- Orders
- Menus
- Reviews
- Favorites
- Wallet
- Payments
- Notifications
- Analytics
- Settings
- REST API
- Admin

Each module should be independent and easily extensible.

## Chef Verification:
- Identity verification
- Required documents upload
- Approval workflow
- Rejection reason
- Resubmission

## Chef Profiles:
Each chef has:
- Name
- Profile photo
- Cover image
- Description
- Cuisine types
- Address
- Delivery radius
- Working hours
- Contact information
- Gallery
- Ratings
- Total reviews
- Number of completed orders

## Menu Management:
Chefs can manage:
- Categories
- Food items
- Images
- Price
- Description
- Preparation time
- Availability
- Tags
- Allergens
- Ingredients

## Search & Filtering:
Customers can filter by:
- Location
- Distance
- Cuisine
- Price
- Rating
- Availability
- Delivery
- Pickup
- Categories

## Order lifecycle:
Pending -> Accepted -> Preparing -> Ready -> Out for delivery -> Completed OR Cancelled

## Payment Support:
- Architecture must support multiple payment methods.
- Initially: Cash on delivery
- Future: Online payments and Payment gateways
- Payments should be modular.

## Commission System:
- Platform commission:
- 10% of every completed order.
- Commission is calculated automatically when an order becomes Completed.
- Each order can only generate commission once.

## Chef Commission Wallet:
- Track how much commission each chef owes the platform.
- This is not a payment wallet.
- Wallet balance increases from completed orders.
- No wallet reset is allowed.
- Admins manually deduct collected amounts.

## wallet statuses:
- empty
- not_ready
- ready_to_collect
- in_progress

## collection threshold:
Current collection threshold: 2000 DA. The threshold must later become configurable from Platform Settings.

## Wallet rules:
- New commissions always increase balance.
- Manual deductions only reduce the balance.
- Never delete commission history.
- Never block future commissions.
- Status updates automatically after every commission or deduction.

## Wallet History:
Store every wallet operation.

-Types:
- - Commission added
- - Manual deduction
- - Manual adjustment
- - System correction

- Every transaction should include:
- - Date
- - Amount
- - Type
- - Related order (if applicable)
- - Admin user (if applicable)
- - Notes

## Notifications:
Notification system should be centralized. Examples:

- Customer:
- - Order accepted
- - Order completed

- Chef:
- - New order
- - New review
- - Wallet ready for collection

- Admin:
- - New chef registration
- - Wallet reached threshold
- - Report submitted

## Analytics Dashboard:

- Chef dashboard:
- - Revenue
- - Completed orders
- - Pending orders
- - Average rating
- - Wallet balance
- - Monthly statistics
- - Top selling dishes

- Admin dashboard:
- - Total orders
- - Platform revenue
- - Total commissions
- - Active chefs
- - Active customers
- - Pending approvals

## Configurable Platform Settings:
- Commission percentage
- Wallet collection threshold
- Currency
- Delivery options
- Notification settings
- Payment methods
- Platform branding

## Customer user role:
- Register and login.
- Browse chefs.
- View chef profiles.
- Search and filter chefs.
- Place food orders.
- Track order status.
- View order history.
- Leave reviews.
- Manage favorite chefs.
- Manage addresses.
- Receive notifications.

## Chef user role:
- Register as a chef.
- Complete profile.
- Upload required verification documents.
- Wait for admin approval.
- Manage menu.
- Manage categories.
- Manage food items.
- Manage availability.
- Accept or reject orders.
- Update order status.
- View earnings.
- View commission wallet.
- View analytics dashboard.
- Receive notifications.

## Administrator user role:
- Manage all users.
- Approve or reject chefs.
- Manage categories.
- Manage platform settings.
- Manage commissions.
- Manage wallets.
- View reports.
- Moderate reviews.
- Handle disputes.
- Configure fees and thresholds.
- Manage notifications.

## Extra Features:
Customers can:
- Rate chefs
- Write reviews
- Edit reviews (optional)
- Report reviews
- Customers can save favorite chefs.

## Future Features (Not in MVP):
- Multiple chefs per order
- Coupons
- Referral system
- Loyalty points
- Mobile application API
- Real-time order tracking
- Subscription plans
- Multi-language
- Multi-currency
- Multi-vendor delivery
- Driver accounts
- Kitchen inventory
- Promotional campaigns
- AI-powered recommendations

---
*Generated for coding agents to streamline WordPress plugin development.*
