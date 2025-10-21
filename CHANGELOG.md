# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2025-10-22

### Fixed
- WordPress coding standards compliance
- Input validation and sanitization improvements
- Database query optimization with proper phpcs ignore comments
- Removed development debug functions (error_log calls)
- Enhanced output escaping for security
- Fixed text domain consistency across all files

### Changed
- Updated nonce verification to include proper sanitization
- Improved POST data handling with wp_unslash()
- Enhanced SCF field name handling consistency

### Security
- Added comprehensive input validation and sanitization
- Enhanced output escaping throughout the codebase
- Improved nonce verification security

## [0.0.1] - Initial Release

### Added
- Initial plugin release
- Custom post type 'andw-news' support
- Template management system
- Shortcode and Gutenberg block support
- Administrative interface
- SCF (Smart Custom Fields) integration
- Multiple display templates (list, tabs, etc.)