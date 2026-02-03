# Publishing Sift to Packagist

This guide walks you through publishing the Sift PHP extension to [Packagist](https://packagist.org), the main Composer package repository.

## Important Note

**Sift is a compiled PHP extension**, not a pure PHP library. Composer cannot install the extension itself - users must compile and install it manually or via PECL. However, publishing to Packagist provides:

- **Discoverability**: Developers can find your extension via Packagist search
- **IDE Support**: Stub files provide autocompletion in IDEs
- **Version Management**: Composer can track version requirements
- **Documentation**: Centralized package information

## Prerequisites

1. **GitHub Account**: You need a public GitHub repository
2. **Packagist Account**: Create a free account at [packagist.org](https://packagist.org)
3. **Git Repository**: Your code must be in a Git repository with proper tags

## Step 1: Create GitHub Repository

1. Go to [GitHub](https://github.com) and create a new repository named `sift`
2. Push your local code to GitHub:

```bash
cd /home/dmytro/RustroverProjects/Sift

# Initialize git if not already done
git init

# Add GitHub remote (replace with your actual GitHub username)
git remote add origin https://github.com/dmytrokucher/sift.git

# Add all files
git add .

# Commit
git commit -m "Initial commit: Sift SIMD-accelerated JSON parser"

# Push to GitHub
git push -u origin main
```

## Step 2: Create a Release Tag

Packagist uses Git tags to determine package versions. Create your first release:

```bash
# Tag the current commit as version 0.1.0
git tag -a v0.1.0 -m "Release v0.1.0: Initial beta release"

# Push the tag to GitHub
git push origin v0.1.0
```

**Version Naming Convention:**
- Use semantic versioning: `MAJOR.MINOR.PATCH`
- Prefix tags with `v` (e.g., `v0.1.0`, `v1.0.0`)
- Match the version in `package.xml`

## Step 3: Submit to Packagist

1. **Log in to Packagist**: Go to [packagist.org](https://packagist.org) and sign in
2. **Submit Package**:
   - Click "Submit" in the top navigation
   - Enter your repository URL: `https://github.com/dmytrokucher/sift`
   - Click "Check" to validate
   - Click "Submit" to publish

3. **Verify Package**: Your package should now be live at:
   ```
   https://packagist.org/packages/dmytrokucher/sift
   ```

## Step 4: Set Up Auto-Updates

Configure GitHub to automatically notify Packagist of new releases:

1. **Get Packagist API Token**:
   - Go to your [Packagist profile](https://packagist.org/profile/)
   - Click "Show API Token" and copy it

2. **Configure GitHub Webhook**:
   - Go to your GitHub repository settings
   - Navigate to "Webhooks" → "Add webhook"
   - Set Payload URL to: `https://packagist.org/api/github?username=dmytrokucher`
   - Set Content type to: `application/json`
   - Set Secret to: Your Packagist API token
   - Select "Just the push event"
   - Click "Add webhook"

Now, whenever you push a new tag to GitHub, Packagist will automatically update!

## Step 5: Usage Instructions for Users

Users can now reference your extension in their `composer.json`:

```json
{
    "require": {
        "dmytrokucher/sift": "^0.1"
    }
}
```

However, they **must still install the extension** separately:

### Installation Instructions to Provide Users:

```markdown
## Installation

### 1. Install the Extension

#### Using PECL (when available):
```bash
pecl install sift
```

#### Manual Build:
```bash
# Install dependencies
sudo apt-get install php-dev

# Clone and build
git clone https://github.com/dmytrokucher/sift.git
cd sift
cargo install cargo-php
cargo php install --release

# Verify installation
php -m | grep sonic
```

### 2. Add to Composer (for IDE support):
```bash
composer require dmytrokucher/sift
```

This provides IDE autocompletion via stub files.
```

## Releasing New Versions

When you're ready to release a new version:

1. **Update version numbers**:
   - Update `package.xml` version
   - Update `docs/CHANGELOG.md`

2. **Commit changes**:
   ```bash
   git add .
   git commit -m "Release v0.2.0: Add new features"
   ```

3. **Create and push tag**:
   ```bash
   git tag -a v0.2.0 -m "Release v0.2.0"
   git push origin main
   git push origin v0.2.0
   ```

4. **Packagist auto-updates**: If webhook is configured, Packagist updates automatically

## Best Practices

### Semantic Versioning

- **MAJOR** (1.0.0): Breaking changes
- **MINOR** (0.1.0): New features, backward compatible
- **PATCH** (0.0.1): Bug fixes, backward compatible

### Pre-release Versions

- **Alpha**: `v0.1.0-alpha.1`
- **Beta**: `v0.1.0-beta.1`
- **RC**: `v0.1.0-rc.1`

### Stability Flags in composer.json

Your `composer.json` currently has:
```json
"minimum-stability": "beta"
```

This allows beta releases. For stable releases, change to:
```json
"minimum-stability": "stable"
```

## Troubleshooting

### Package Not Found
- Verify the repository URL is correct
- Ensure at least one valid tag exists
- Check that `composer.json` is valid: `composer validate`

### Auto-Update Not Working
- Verify GitHub webhook is configured correctly
- Check webhook delivery logs in GitHub settings
- Ensure Packagist API token is correct

### Invalid composer.json
- Run `composer validate` to check for errors
- Ensure JSON syntax is valid
- Verify all required fields are present

## Additional Resources

- [Packagist Documentation](https://packagist.org/about)
- [Composer Documentation](https://getcomposer.org/doc/)
- [Semantic Versioning](https://semver.org/)
- [GitHub Releases](https://docs.github.com/en/repositories/releasing-projects-on-github)
