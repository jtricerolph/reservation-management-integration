# CRITICAL: Development Checklist for ALL Sessions

**⚠️ THIS IS A MANDATORY CHECKLIST FOR EVERY DEVELOPMENT SESSION ⚠️**

## Pre-Session Review (ALWAYS CHECK)

### Version Information
- [ ] **Current Plugin Version**: Check plugin header in `reservation-management-integration.php`
  - Current: v1.1.15 (as of 2025-10-31)
- [ ] **JS_VERSION constant**: Check in main plugin file
  - Current: v1.0.75 (as of 2025-10-31)
- [ ] **CSS_VERSION constant**: Check in main plugin file
  - Current: v1.0.75 (as of 2025-10-31)
- [ ] **Last CHANGELOG.md entry**: Verify last documented change
  - Last Entry Date: 2025-10-31

### API Environment
- [ ] **Verify API Mode**: Check WordPress admin settings
  - [ ] Production (Live API calls)
  - [ ] Testing (Preview with confirmation)
  - [ ] Sandbox (Preview only, no execution)
- [ ] **Review API_PREVIEW_PARITY.md**: Understand preview/execute synchronization requirements

### Critical Documents Review
- [ ] **Read SESSION_CONTEXT.md**: Check for known issues and current state
- [ ] **Review FUNCTION_REFERENCE.md**: Familiarize with current functions
- [ ] **Check API_DOCUMENTATION.md**: Note any new endpoints needed

---

## During Development - EVERY Change

### Code Changes
- [ ] **Version Bumping**:
  - [ ] Update plugin header version for ANY change
  - [ ] Update JS_VERSION if modifying `staying-today.js`
  - [ ] Update CSS_VERSION if modifying `style.css`
  - [ ] Use semantic versioning (major.minor.patch)

### API Development Rules
- [ ] **For ALL API changes - CRITICAL**:
  - [ ] Update EXECUTE function
  - [ ] Update PREVIEW function (must match execute)
  - [ ] Test in all three modes: Production, Testing, Sandbox
  - [ ] Document new endpoints in API_DOCUMENTATION.md
  - [ ] Include request/response examples

### Testing Requirements
- [ ] **Test in ALL three API modes**:
  - [ ] Production mode - verify actual execution
  - [ ] Testing mode - verify preview dialog accuracy
  - [ ] Sandbox mode - verify preview without execution
- [ ] **Verify preview/execute parity**:
  - [ ] Preview dialog shows EXACTLY what will be sent
  - [ ] All fields present in both functions
  - [ ] Data transformation identical

### Function Documentation
- [ ] **Update FUNCTION_REFERENCE.md for**:
  - [ ] New functions added
  - [ ] Functions modified
  - [ ] Functions removed
  - [ ] Line number changes (if significant)

---

## API-Specific Checklists

### When Creating New API Integration
- [ ] **Request Documentation from User**:
  - [ ] Ask for endpoint URL
  - [ ] Ask for authentication method
  - [ ] Request sample request body
  - [ ] Request sample response
  - [ ] Ask about rate limits
  - [ ] Document any special headers required

### When Modifying Resos API Calls
- [ ] **Update both functions**:
  - [ ] `ajax_create_resos_booking()` AND `ajax_preview_resos_create()`
  - [ ] `ajax_confirm_resos_match()` AND `ajax_preview_resos_match()`
- [ ] **Update JavaScript**:
  - [ ] FormData in execute path
  - [ ] FormData in preview path
- [ ] **Test dietary requirements**: Dynamic loading still works
- [ ] **Test booking notes**: Notes endpoint still functions

### When Modifying Newbook API Calls
- [ ] **Check group access**: `get_group_details()` still disabled?
- [ ] **Verify authentication**: Basic auth + API key header
- [ ] **Test region-specific endpoints**: URL uses correct region

---

## End of Session - MANDATORY

### Documentation Updates
- [ ] **Update CHANGELOG.md**:
  - [ ] Add entry with version number
  - [ ] Include date
  - [ ] List all changes (Major Features, Changed, Fixed, Technical Details)
  - [ ] Note files modified with version numbers
  - [ ] NO GAPS - every version bump must have an entry

- [ ] **Update FUNCTION_REFERENCE.md**:
  - [ ] Add new functions with line numbers
  - [ ] Update modified functions
  - [ ] Remove deleted functions
  - [ ] Update dependencies

- [ ] **Update SESSION_CONTEXT.md**:
  - [ ] Current version numbers
  - [ ] Work completed this session
  - [ ] Known issues or workarounds
  - [ ] Next steps planned

- [ ] **Update API_DOCUMENTATION.md**:
  - [ ] New endpoints documented
  - [ ] Request/response examples added
  - [ ] Error codes documented
  - [ ] Rate limits noted

### Verification
- [ ] **Preview/Execute Parity Check**:
  - [ ] Diff preview and execute functions
  - [ ] Verify identical data transformation
  - [ ] Test preview accuracy

- [ ] **Asset Version Check**:
  - [ ] JS file has correct version in comments
  - [ ] CSS file has correct version in comments
  - [ ] Enqueued with correct version constants

### Git Commit
- [ ] **Stage all changes**: `git add .`
- [ ] **Commit with descriptive message**:
  ```bash
  git commit -m "v1.1.16: [Brief description of changes]

  - Feature: [what was added]
  - Fixed: [what was fixed]
  - Updated: [what was modified]"
  ```
- [ ] **Update commit reference in SESSION_CONTEXT.md**

---

## Common Pitfalls to Avoid

### ❌ DO NOT FORGET
1. **Version bumping** - Every change needs a version increment
2. **Changelog updates** - Every version needs documentation
3. **Preview/execute parity** - Both must match EXACTLY
4. **Testing all modes** - Production, Testing, AND Sandbox
5. **Function documentation** - New functions must be documented

### ⚠️ WATCH OUT FOR
1. **JavaScript changes** without updating JS_VERSION
2. **CSS changes** without updating CSS_VERSION
3. **API changes** without updating preview function
4. **New endpoints** without documentation
5. **Missing CHANGELOG entries** (creates gaps)

---

## Quick Reference Commands

### Check Current Versions
```bash
# Plugin version
grep "Version:" hotel-admin.php | head -1

# JS version
grep "JS_VERSION" hotel-admin.php

# CSS version
grep "CSS_VERSION" hotel-admin.php
```

### Test API Modes
1. Switch to Testing mode in WordPress admin
2. Perform action - verify preview dialog
3. Switch to Sandbox mode
4. Perform action - verify preview without execution
5. Switch to Production mode
6. Perform action - verify live execution

### Git Workflow
```bash
# Start of session
git status
git pull

# During development
git add -p  # Review changes piece by piece
git diff --staged  # Review what will be committed

# End of session
git add .
git commit -m "Descriptive message"
git push
```

---

## Emergency Rollback

If something breaks in production:

1. **Immediate Rollback**:
   ```bash
   cp /var/www/html/wp-content/plugins/BACKUP/v1.1.15_2025-10-31/hotel-admin.php ./
   cp -r /var/www/html/wp-content/plugins/BACKUP/v1.1.15_2025-10-31/assets/* ./assets/
   ```

2. **Check functionality**

3. **Document issue in SESSION_CONTEXT.md**

4. **Fix and re-test thoroughly**

---

## Remember

> **"The difference between a bug and a feature is documentation."**

Always document your changes. Future you (and other developers) will thank you.

---

*Last Updated: 2025-10-31*
*This checklist version: 1.0.0*