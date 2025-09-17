# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2025-09-18

### Added
- Enhanced Facade class with new methods:
  - `isResolved()` - Check if the facade has been resolved
  - `getServiceId()` - Get the service ID for this facade
  - `call()` - Call a method on the facade instance with an array of arguments
  - `hasMethod()` - Check if a method exists on the facade instance
- Enhanced FacadeProxy class with new methods:
  - `isBound()` - Check if a facade is bound to a service ID
  - `getServiceId()` - Get the service ID for a facade
  - `getBindings()` - Get all bound facades

### Changed
- Improved MailFacade with better IDE support by adding `@see` annotation

## [1.0.0] - 2025-09-15

### Added
- Initial release of KodePHP Facade Component
- Core Facade abstract class with static proxy functionality
- FacadeProxy manager for handling facade to service mappings
- Basic examples and documentation