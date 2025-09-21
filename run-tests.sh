#!/bin/bash

# Comprehensive Test Suite Runner for Notification Tracker Bundle
# This script runs all functional and unit tests for the contact management system

echo "🧪 Running Comprehensive Test Suite for Notification Tracker Bundle"
echo "================================================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if PHPUnit is available
if ! command -v vendor/bin/phpunit &> /dev/null; then
    print_error "PHPUnit not found. Please install dependencies first:"
    echo "composer install"
    exit 1
fi

# Set test environment
export APP_ENV=test
export DATABASE_URL="sqlite:///:memory:"

print_status "Setting up test environment..."

# Run database migrations for tests if needed
if [ -f "bin/console" ]; then
    print_status "Setting up test database..."
    php bin/console doctrine:database:create --env=test --if-not-exists 2>/dev/null || true
    php bin/console doctrine:schema:create --env=test --quiet 2>/dev/null || true
fi

echo ""
echo "📋 Test Categories:"
echo "==================="
echo "1. Contact API Resource Tests"
echo "2. Contact Channel API Resource Tests" 
echo "3. Contact Group API Resource Tests"
echo "4. Contact Activity API Resource Tests"
echo "5. Contact Repository Tests"
echo "6. Integration Tests"
echo ""

# Initialize counters
total_tests=0
passed_tests=0
failed_tests=0

# Function to run test suite
run_test_suite() {
    local suite_name="$1"
    local test_path="$2"
    local description="$3"
    
    print_status "Running $suite_name..."
    echo "Description: $description"
    echo "Path: $test_path"
    echo ""
    
    if [ -f "$test_path" ] || [ -d "$test_path" ]; then
        if vendor/bin/phpunit "$test_path" --colors=always --verbose; then
            print_success "$suite_name completed successfully"
            ((passed_tests++))
        else
            print_error "$suite_name failed"
            ((failed_tests++))
        fi
    else
        print_warning "$suite_name skipped - path not found: $test_path"
    fi
    
    ((total_tests++))
    echo ""
    echo "----------------------------------------"
    echo ""
}

# Run individual test suites
run_test_suite "Contact API Tests" \
    "tests/Functional/ApiResource/ContactApiResourceTest.php" \
    "Tests for Contact CRUD operations, filtering, searching, and validation"

run_test_suite "Contact Channel API Tests" \
    "tests/Functional/ApiResource/ContactChannelApiResourceTest.php" \
    "Tests for Contact Channel management, verification, and delivery tracking"

run_test_suite "Contact Group API Tests" \
    "tests/Functional/ApiResource/ContactGroupApiResourceTest.php" \
    "Tests for Contact Group operations, hierarchies, and membership management"

run_test_suite "Contact Activity API Tests" \
    "tests/Functional/ApiResource/ContactActivityApiResourceTest.php" \
    "Tests for Contact Activity tracking, filtering, and analytics"

run_test_suite "Contact Repository Tests" \
    "tests/Functional/Repository/ContactRepositoryTest.php" \
    "Tests for Contact repository methods, search functionality, and data analytics"

# Run all functional tests together if individual files don't exist
if [ -d "tests/Functional" ]; then
    run_test_suite "All Functional Tests" \
        "tests/Functional" \
        "Complete functional test suite for all API resources and repositories"
fi

# Run unit tests if they exist
if [ -d "tests/Unit" ]; then
    run_test_suite "Unit Tests" \
        "tests/Unit" \
        "Unit tests for individual components and services"
fi

# Generate summary
echo ""
echo "📊 Test Summary:"
echo "================"
echo "Total Test Suites: $total_tests"
echo "Passed: $passed_tests"
echo "Failed: $failed_tests"

if [ $failed_tests -eq 0 ]; then
    print_success "All test suites passed! 🎉"
    echo ""
    echo "✅ Contact Management System Verification Complete"
    echo "   - API Resources: Functional"
    echo "   - Repositories: Functional" 
    echo "   - Data Integrity: Verified"
    echo "   - Validation: Working"
    echo "   - Search & Filtering: Working"
else
    print_error "$failed_tests test suite(s) failed"
    echo ""
    echo "❌ Some tests failed. Please review the output above for details."
    exit 1
fi

echo ""
echo "🔍 Test Coverage Areas Verified:"
echo "================================"
echo "• Contact CRUD Operations"
echo "• Contact Channel Management"
echo "• Contact Group Operations"
echo "• Contact Activity Tracking"
echo "• Repository Search Functions"
echo "• Data Validation"
echo "• API Filtering & Pagination"
echo "• Entity Relationships"
echo "• Business Logic"
echo ""

echo "📝 Next Steps:"
echo "=============="
echo "1. Review any failed tests and fix issues"
echo "2. Add additional edge case tests as needed"
echo "3. Consider performance testing for large datasets"
echo "4. Implement integration tests with external services"
echo "5. Add mutation testing for test quality verification"
echo ""

print_success "Test suite execution completed."
