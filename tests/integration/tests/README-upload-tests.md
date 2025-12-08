# Upload Path Tests

This document describes how to run the upload path tests for issue #136.

## Running All Upload Path Tests

To run all upload path tests:

```bash
vendor/bin/phpunit --group=upload
```

## Running Specific Test Groups

### All upload path tests (core functionality)
```bash
vendor/bin/phpunit --group=upload-paths
```

### Files rewriting tests
```bash
vendor/bin/phpunit --group=files-rewriting
```

### Tests without files rewriting
```bash
vendor/bin/phpunit --group=no-files-rewriting
```

### Path structure validation tests
```bash
vendor/bin/phpunit --group=path-structure
```

### Path preservation tests
```bash
vendor/bin/phpunit --group=path-preservation
```

### Subdirectory installation tests
```bash
vendor/bin/phpunit --group=subdirectory
```

### Multiple sites tests
```bash
vendor/bin/phpunit --group=multiple-sites
```

### Upload URL tests
```bash
vendor/bin/phpunit --group=upload-urls
```

### Network cloning tests
```bash
vendor/bin/phpunit --group=network-cloning
```

## Running Tests by Ticket Number

To run all tests related to ticket/issue #136:

```bash
vendor/bin/phpunit --group=136
```

## Running a Single Test Class

To run only the upload path test class:

```bash
vendor/bin/phpunit tests/integration/tests/test-upload-paths.php
```

## Running a Single Test Method

To run a specific test method:

```bash
vendor/bin/phpunit --filter test_upload_path_without_duplication
```

## Test Coverage

The upload path tests cover:

- **Basic functionality**: Upload paths are set correctly without duplication
- **Files rewriting**: Behavior with `ms_files_rewriting` enabled and disabled
- **Multisite configurations**: Standard multisite setups
- **WordPress versions**: Tests run on modern WordPress (3.7+)
- **Path structure**: Validation of proper path format (no consecutive slashes, proper structure)
- **Path preservation**: Existing correct paths are not overwritten
- **Subdirectory installs**: Networks with subdirectory paths
- **Multiple sites**: Consistency across multiple sites in the same network
- **Upload URLs**: Consistency between upload paths and URLs
- **Network cloning**: Upload paths when cloning networks

## Debugging

To see more verbose output while running tests:

```bash
vendor/bin/phpunit --group=upload --verbose
```

To run tests with debug output:

```bash
vendor/bin/phpunit --group=upload --debug
```
