# Coding Agent Instructions for WordPress Plugin Development

## Project details
- Name: MaklaPlace WordPress Plugin
- Slug: maklaplace
- Description: MaklaPlace is a WordPress plugin that transforms WordPress into a marketplace connecting customers with independent chefs.
The plugin should be modular, scalable, secure, and built using WordPress best practices. Every major feature should be developed as an independent module to simplify future maintenance and expansion.
- Author: Yazid Bouzifi

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

## Environment Details
- OS: Windows 11
- PHP binary: C:\Program Files (x86)\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win64\php.exe
- PHP version: 8.2.29
- php.ini used: C:\Users\Yazid\php.ini (see note below for workspace workaround)
- WP_CLI phar path: phar://C:/wp-cli/wp-cli.phar
- WP-CLI version: 2.12.0
- WordPress folder: C:\Users\Yazid\Local Sites\01\app\public\
- Site URL: http://01.local

> **NOTE ABOUT PHP.INI**: The default php.ini at C:\Users\Yazid\php.ini has an incorrect extension_dir setting.
> It points to an inaccessible directory: C:\Users\Yazid\AppData\Roaming\Local\lightning-services\php-8.2.29+0\bin\win64\ext
> To use PHP and WP-CLI correctly, use the workspace-specific php.ini:
> C:\Users\Yazid\Documents\Codex\2026-06-19\to-streamline-wordpress-development-you-must-3\php_override\php.ini
> All PHP and WP-CLI commands should include the -c flag pointing to this file.

## Development Workflow
1. **Navigate to WordPress root**:
   powershell
   cd '.\Local Sites\01\app\public\'
   

2. **Run WP-CLI commands** 
   using the wrapper batch file:
   powershell
   & "C:\Program Files (x86)\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win64\php.exe" -c "C:\Users\Yazid\Documents\Codex\2026-06-19\to-streamline-wordpress-development-you-must-3\php_override\php.ini" "C:\Program Files (x86)\Local\resources\extraResources\bin\wp-cli\wp-cli.phar" --path="C:\Users\Yazid\Local Sites\01\app\public" <command>
   

3. **Run PHP commands**:
   powershell
   & "C:\Program Files (x86)\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win64\php.exe" -c "C:\Users\Yazid\Documents\Codex\2026-06-19\to-streamline-wordpress-development-you-must-3\php_override\php.ini" <command>
   

4. **Plugin directory**:
   
   C:\Users\Yazid\Local Sites\01\app\public\wp-content\plugins\maklaplace\
   

5. **Common WP-CLI commands**:
   - List plugins: & "C:\Program Files (x86)\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win64\php.exe" -c "C:\Users\Yazid\Documents\Codex\2026-06-19\to-streamline-wordpress-development-you-must-3\php_override\php.ini" "C:\Program Files (x86)\Local\resources\extraResources\bin\wp-cli\wp-cli.phar" --path="C:\Users\Yazid\Local Sites\01\app\public" plugin list
   - Activate plugin: & "C:\Program Files (x86)\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win64\php.exe" -c "C:\Users\Yazid\Documents\Codex\2026-06-19\to-streamline-wordpress-development-you-must-3\php_override\php.ini" "C:\Program Files (x86)\Local\resources\extraResources\bin\wp-cli\wp-cli.phar" --path="C:\Users\Yazid\Local Sites\01\app\public" plugin activate <slug>
   - Deactivate plugin: & "C:\Program Files (x86)\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win64\php.exe" -c "C:\Users\Yazid\Documents\Codex\2026-06-19\to-streamline-wordpress-development-you-must-3\php_override\php.ini" "C:\Program Files (x86)\Local\resources\extraResources\bin\wp-cli\wp-cli.phar" --path="C:\Users\Yazid\Local Sites\01\app\public" plugin deactivate <slug>
   - Update plugin: & "C:\Program Files (x86)\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win64\php.exe" -c "C:\Users\Yazid\Documents\Codex\2026-06-19\to-streamline-wordpress-development-you-must-3\php_override\php.ini" "C:\Program Files (x86)\Local\resources\extraResources\bin\wp-cli\wp-cli.phar" --path="C:\Users\Yazid\Local Sites\01\app\public" plugin update <slug>
   - Install plugin: & "C:\Program Files (x86)\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win64\php.exe" -c "C:\Users\Yazid\Documents\Codex\2026-06-19\to-streamline-wordpress-development-you-must-3\php_override\php.ini" "C:\Program Files (x86)\Local\resources\extraResources\bin\wp-cli\wp-cli.phar" --path="C:\Users\Yazid\Local Sites\01\app\public" plugin install <slug-or-zip>

6. **Debugging**:
   - Enable WP_DEBUG in wp-config.php for development
   - Check the WordPress debug log at C:\Users\Yazid\Local Sites\01\app\public\wp-content\debug.log and fix errors periodically.
   - Use WP-CLI with custom php.ini: & "C:\Program Files (x86)\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win64\php.exe" -c "C:\Users\Yazid\Documents\Codex\2026-06-19\to-streamline-wordpress-development-you-must-3\php_override\php.ini" "C:\Program Files (x86)\Local\resources\extraResources\bin\wp-cli\wp-cli.phar" --path="C:\Users\Yazid\Local Sites\01\app\public" scaffold <command>

7. **Testing**:
   - Visit local site: http://01.local
   - Admin dashboard: http://01.local/wp-admin
   - Check the WordPress debug log at C:\Users\Yazid\Local Sites\01\app\public\wp-content\debug.log and fix errors periodically.

## Deployment
- Ensure plugin works with PHP 8.2+ (tested with 8.2.29)
- Check compatibility with latest WordPress version
- Check the WordPress debug log at C:\Users\Yazid\Local Sites\01\app\public\wp-content\debug.log and fix errors periodically.

## Troubleshooting
- If WP-CLI reports errors, Don't finish until you fix the error
- For path issues, use short 8.3 names or quote paths with spaces
- When running commands from different shells, ensure you reference the correct PHP binary
- Check the WordPress debug log at C:\Users\Yazid\Local Sites\01\app\public\wp-content\debug.log and fix errors periodically.
- **PHP Extension Loading Issues**: If you see "Access is denied" errors when loading PHP extensions, check your php.ini extension_dir setting. The default php.ini points to an inaccessible directory. Use the workspace php.ini with correct extension_dir:
  extension_dir="C:/Program Files (x86)/Local/resources/extraResources/lightning-services/php-8.2.29+0/bin/win64/ext"

## Rules:
- Never modify WordPress core.
- Never modify themes.
- Never modify other plugins.
- Only edit files in this workspace.
- Validate all changed PHP files with .'C:\Program Files (x86)\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win64\php.exe' -c 'C:\Users\Yazid\Documents\Codex\2026-06-19\to-streamline-wordpress-development-you-must-3\php_override\php.ini' -l.
- Check the WordPress debug log at C:\Users\Yazid\Local Sites\01\app\public\wp-content\debug.log and fix errors periodically.

## Security:
- WordPress coding standards
- Nonces
- Capability checks
- Prepared SQL statements
- Data sanitization
- Data validation
- Escaping output
- CSRF protection

## Performance:
- Optimized database queries
- Pagination
- Lazy loading where appropriate
- Caching support
- Minimal database writes

## Database:
- Use custom database tables only where justified by performance or relational complexity (e.g., orders, wallet transactions). Use WordPress post types, taxonomies, user meta, and options where appropriate. Design for scalability and avoid unnecessary duplication of data.

## Coding Standards:
- PHP 8.2+
- WordPress Coding Standards
- PSR-4 autoloading
- Object-Oriented Programming
- SOLID principles where practical
- Namespaced classes
- Comprehensive inline documentation
- Internationalization (i18n)
- Translation-ready

## Development Principles:
- Build one module at a time.
- Each module must be fully tested before starting the next.
- Keep modules loosely coupled and highly cohesive.
- Minimize breaking changes.
- Maintain backward compatibility where possible.
- Write maintainable, well-documented code.
- Optimize for scalability from the beginning.
- Favor extensibility over quick fixes.
- Reuse WordPress APIs whenever practical instead of reinventing existing functionality.

---
*Generated for coding agents to streamline WordPress plugin development in this specific environment.*
