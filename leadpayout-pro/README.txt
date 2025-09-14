=== LeadPayout Pro ===
Contributors: leadpayout-team
Tags: microtasks, referrals, payments, stripe, monetization
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A comprehensive microtask and referral system with Stripe integration for WordPress.

== Description ==

LeadPayout Pro is a powerful WordPress plugin that transforms your website into a complete microtask and referral platform. Users can complete simple tasks to earn money, refer friends for commissions, and withdraw their earnings through Stripe integration.

**Key Features:**

🔌 **Universal Theme Compatibility**
- Uses WordPress default UI styles for seamless integration
- Supports both Classic and Block editor themes
- Proper enqueue methods for all styles and scripts

🧩 **Comprehensive Admin Dashboard**
- Complete admin interface with 8 dedicated pages
- Task creation and management system
- Real-time approval workflow
- Advanced analytics and reporting

✅ **One-Click Frontend Setup**
- Automatically creates frontend pages on activation
- Ready-to-use shortcodes for all functionality
- Mobile-responsive design
- User-friendly interface

💸 **Advanced Task System**
- Multiple task types (share link, watch video, install app, etc.)
- Flexible payout system ($0.10 to $2.00 per task)
- Budget management and tracking
- Proof requirement system

🛠️ **Smart Approval System**
- Manual and automatic approval options
- IP tracking and duplicate prevention
- Review notes and rejection reasons
- Bulk approval actions

💳 **Full Stripe Integration**
- Account funding via Stripe payments
- Automated withdrawal processing
- Test and live mode support
- Secure payment handling

👥 **Powerful Referral System**
- Unique referral codes for each user
- Configurable commission rates
- Multi-level tracking
- Social sharing tools

📊 **Analytics & Leaderboards**
- Weekly and monthly leaderboards
- Comprehensive user statistics
- Admin analytics dashboard
- Export functionality

🛡️ **Security & Best Practices**
- WordPress coding standards compliance
- Proper nonce verification
- User capability checks
- SQL injection prevention

== Installation ==

1. Upload the `leadpayout-pro` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your Stripe settings in LeadPayout > Settings
4. Start creating tasks and let users earn money!

The plugin automatically creates the following pages on activation:
- My Tasks
- My Earnings  
- Refer & Earn

== Frequently Asked Questions ==

= What payment methods are supported? =

Currently, the plugin supports Stripe for both funding accounts and processing withdrawals. Users can add funds via credit/debit cards and withdraw to their bank accounts.

= Can I customize the task types? =

Yes, the plugin includes several built-in task types, and the system is designed to be easily extensible for custom task types.

= Is there a minimum withdrawal amount? =

Yes, the default minimum withdrawal is $5.00, but this can be configured in the settings.

= How does the referral system work? =

Each user gets a unique referral code. When someone signs up using their code, the referrer earns a commission on all the referred user's task completions.

= Can I run this on multiple sites? =

Each installation requires its own license and Stripe configuration.

== Screenshots ==

1. Admin Dashboard - Overview of all plugin statistics
2. Task Creation Interface - Easy task setup with all options
3. Task Approval System - Review and approve user submissions
4. User Dashboard - Clean interface for users to track earnings
5. Referral System - Powerful tools for growing your user base
6. Settings Panel - Complete configuration options

== Changelog ==

= 1.0.0 =
* Initial release
* Complete microtask system
* Referral functionality
* Stripe integration
* Admin dashboard
* User frontend pages
* Email notifications
* Leaderboard system
* Security features

== Upgrade Notice ==

= 1.0.0 =
Initial release of LeadPayout Pro. Install and start monetizing your website today!

== Configuration ==

After activation, follow these steps:

1. **Stripe Setup:**
   - Go to LeadPayout > Settings
   - Add your Stripe API keys (test mode for development)
   - Configure minimum/maximum payout amounts

2. **Create Your First Task:**
   - Navigate to LeadPayout > Create Task
   - Fill in task details and requirements
   - Set payout amount and total budget

3. **Customize Settings:**
   - Adjust referral commission rates
   - Enable/disable auto-approval
   - Configure email notifications

4. **Test the System:**
   - Visit the frontend pages created by the plugin
   - Test the complete user flow
   - Verify payments and withdrawals work correctly

== Support ==

For support and documentation, please contact: felixames0808@gmail.com

== Developer Notes ==

This plugin follows WordPress coding standards and includes:
- Comprehensive security measures
- Extensible architecture
- Clean, documented code
- Database optimization
- Mobile-responsive design

The plugin creates the following database tables:
- wp_leadpayout_tasks
- wp_leadpayout_submissions  
- wp_leadpayout_earnings
- wp_leadpayout_referrals
- wp_leadpayout_transactions
- wp_leadpayout_balances

All tables include proper indexes and foreign key relationships for optimal performance.