# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

EspoCRM is an open-source CRM platform with a single-page application frontend and REST API backend written in PHP. It follows SOLID principles, uses dependency injection, and has a metadata-driven architecture.

## Build Commands

### Frontend Build
- `npm run build` - Full production build
- `npm run build-dev` - Development build
- `npm run build-test` - Build for testing
- `npm run build-frontend` - Build only frontend assets (internal use)

### Backend Commands
- `npm run sa` - Run PHPStan static analysis (level 8)
- `npm run unit-tests` - Run unit tests via PHPUnit
- `npm run integration-tests` - Run integration tests

### Grunt Tasks
- `grunt` - Full build
- `grunt dev` - Development build
- `grunt offline` - Build without composer install
- `grunt internal` - Build only libs and CSS
- `grunt release` - Full build with upgrade packages
- `grunt test` - Build for test running

## Architecture

### Directory Structure
- `application/Espo/` - Core backend application code
  - `Controllers/` - REST API controllers
  - `Services/` - Business logic services
  - `Entities/` - Entity definitions
  - `Repositories/` - Data access layer
  - `Core/` - Framework core components
  - `ORM/` - Object-relational mapping
  - `Resources/metadata/` - Metadata definitions (entityDefs, clientDefs, scopes, etc.)
- `client/src/` - Frontend JavaScript application
  - `views/` - Backbone.js views
  - `controllers/` - Frontend controllers
  - `models/` - Frontend models
  - `collections/` - Frontend collections
- `custom/Espo/` - Customizations and modules
  - `Custom/` - Custom code overrides
  - `Modules/` - Custom modules
- `modules/crm/` - CRM module
- `frontend/` - Frontend build configuration and LESS styles

### Key Files
- `composer.json` - PHP dependencies (requires PHP 8.2-8.4)
- `package.json` - Node.js dependencies (requires Node >=17, npm >=8)
- `phpstan.neon` - PHPStan configuration (level 8)
- `phpunit.xml` - PHPUnit test configuration
- `frontend/bundle-config.json` - Frontend bundling configuration
- `bin/command` - CLI command entry point

## Development Workflow

### Testing
Run tests after making changes:
```bash
npm run unit-tests
npm run integration-tests
```

### Static Analysis
Ensure code quality with PHPStan:
```bash
npm run sa
```

### Frontend Development
The frontend uses Backbone.js with a custom bundling system. JavaScript modules are organized into chunks (main, admin, crm, chart, calendar, timeline) for optimized loading.

### Backend Development
- Follow PSR-4 autoloading standards
- Use dependency injection via the Container
- Metadata-driven development - many features configured via JSON metadata
- Entity definitions in `metadata/entityDefs/`
- Client-side definitions in `metadata/clientDefs/`

### CLI Commands
EspoCRM has a CLI interface accessible via:
```bash
php bin/command [command-name]
```

## Important Notes

- The application uses metadata extensively - check `Resources/metadata/` for entity, field, and UI configurations
- Frontend and backend are loosely coupled via REST API
- Customizations should go in `custom/Espo/Custom/` to survive upgrades
- The build process uses Grunt for asset compilation and bundling
- PHPStan is configured at the highest level (8) for strict type checking