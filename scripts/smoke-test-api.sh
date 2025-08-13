#!/bin/bash

# LivroLog API Smoke Tests
# Quick validation of critical API endpoints after deployment

set -e  # Exit on any error

# Configuration
API_URL="${API_URL:-http://localhost:8000}"
TIMEOUT="${TIMEOUT:-10}"
RETRIES="${RETRIES:-3}"
RETRY_DELAY="${RETRY_DELAY:-2}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test results tracking
TESTS_PASSED=0
TESTS_FAILED=0
FAILED_TESTS=()

# Helper functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[PASS]${NC} $1"
    ((TESTS_PASSED++))
}

log_error() {
    echo -e "${RED}[FAIL]${NC} $1"
    ((TESTS_FAILED++))
    FAILED_TESTS+=("$1")
}

log_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

# HTTP request with retry logic
http_request() {
    local url="$1"
    local expected_status="${2:-200}"
    local method="${3:-GET}"
    local headers="${4:-}"
    
    for i in $(seq 1 $RETRIES); do
        local response
        local status
        
        if [[ -n "$headers" ]]; then
            response=$(curl -s -w "%{http_code}" -X "$method" -H "$headers" --connect-timeout "$TIMEOUT" "$url" 2>/dev/null || echo "000")
        else
            response=$(curl -s -w "%{http_code}" -X "$method" --connect-timeout "$TIMEOUT" "$url" 2>/dev/null || echo "000")
        fi
        
        status="${response: -3}"
        body="${response%???}"
        
        if [[ "$status" == "$expected_status" ]]; then
            echo "$body"
            return 0
        fi
        
        if [[ $i -lt $RETRIES ]]; then
            log_warning "Request failed (attempt $i/$RETRIES), retrying in ${RETRY_DELAY}s..."
            sleep $RETRY_DELAY
        fi
    done
    
    echo "HTTP_ERROR_$status"
    return 1
}

# Validate JSON response
validate_json() {
    local json="$1"
    local required_fields="$2"
    
    if ! echo "$json" | jq . >/dev/null 2>&1; then
        return 1
    fi
    
    if [[ -n "$required_fields" ]]; then
        for field in $required_fields; do
            if ! echo "$json" | jq -e ".$field" >/dev/null 2>&1; then
                log_error "Missing required field: $field"
                return 1
            fi
        done
    fi
    
    return 0
}

# Test functions
test_health_endpoint() {
    log_info "Testing health endpoint..."
    
    local response
    response=$(http_request "$API_URL/health" 200)
    
    if [[ $? -eq 0 ]]; then
        if validate_json "$response" "status timestamp services"; then
            local status
            status=$(echo "$response" | jq -r '.status')
            
            if [[ "$status" == "healthy" ]]; then
                local db_status
                local cache_status
                db_status=$(echo "$response" | jq -r '.services.database')
                cache_status=$(echo "$response" | jq -r '.services.cache')
                
                if [[ "$db_status" == "true" && "$cache_status" == "true" ]]; then
                    log_success "Health endpoint - All services healthy"
                else
                    log_error "Health endpoint - Some services unhealthy (DB: $db_status, Cache: $cache_status)"
                fi
            else
                log_error "Health endpoint - Status is '$status', expected 'healthy'"
            fi
        else
            log_error "Health endpoint - Invalid JSON response structure"
        fi
    else
        log_error "Health endpoint - Request failed: $response"
    fi
}

test_showcase_endpoint() {
    log_info "Testing showcase endpoint..."
    
    local response
    response=$(http_request "$API_URL/showcase" 200)
    
    if [[ $? -eq 0 ]]; then
        if validate_json "$response"; then
            log_success "Showcase endpoint - Response valid"
        else
            log_error "Showcase endpoint - Invalid JSON response"
        fi
    else
        log_error "Showcase endpoint - Request failed: $response"
    fi
}

test_books_search() {
    log_info "Testing books search endpoint..."
    
    local response
    response=$(http_request "$API_URL/books/search?q=test" 401)
    
    if [[ $? -eq 0 ]]; then
        if validate_json "$response" "message"; then
            log_success "Books search endpoint - Properly protected (401 Unauthorized)"
        else
            log_error "Books search endpoint - Invalid error response format"
        fi
    else
        log_error "Books search endpoint - Unexpected response: $response"
    fi
}

test_auth_me_without_token() {
    log_info "Testing auth/me endpoint without token..."
    
    local response
    response=$(http_request "$API_URL/auth/me" 401)
    
    if [[ $? -eq 0 ]]; then
        if validate_json "$response" "message"; then
            log_success "Auth/me endpoint - Properly protected (401 Unauthorized)"
        else
            log_error "Auth/me endpoint - Invalid error response format"
        fi
    else
        log_error "Auth/me endpoint - Unexpected response: $response"
    fi
}

test_cors_headers() {
    log_info "Testing CORS headers..."
    
    local headers
    headers=$(curl -s -I --connect-timeout "$TIMEOUT" "$API_URL/health" 2>/dev/null | grep -i "access-control" || echo "")
    
    if [[ -n "$headers" ]]; then
        log_success "CORS headers - Present"
    else
        log_warning "CORS headers - Not found (may be intentional)"
    fi
}

test_api_docs() {
    log_info "Testing API documentation endpoint..."
    
    local response
    response=$(http_request "$API_URL/documentation" 200 "GET")
    
    if [[ $? -eq 0 ]]; then
        if [[ "$response" == *"swagger"* ]] || [[ "$response" == *"openapi"* ]] || [[ "$response" == *"api"* ]]; then
            log_success "API documentation - Available"
        else
            log_warning "API documentation - Unexpected content"
        fi
    else
        log_warning "API documentation - Not accessible (status: ${response#*_})"
    fi
}

# Main execution
main() {
    echo "========================================="
    echo "🧪 LivroLog API Smoke Tests"
    echo "========================================="
    echo "API URL: $API_URL"
    echo "Timeout: ${TIMEOUT}s"
    echo "Retries: $RETRIES"
    echo "========================================="
    echo ""
    
    # Check if API is reachable
    log_info "Checking API connectivity..."
    if ! curl -s --connect-timeout 5 "$API_URL" >/dev/null 2>&1; then
        log_error "Cannot reach API at $API_URL"
        exit 1
    fi
    log_success "API is reachable"
    echo ""
    
    # Run tests
    test_health_endpoint
    test_showcase_endpoint
    test_books_search
    test_auth_me_without_token
    test_cors_headers
    test_api_docs
    
    echo ""
    echo "========================================="
    echo "📊 Test Results Summary"
    echo "========================================="
    echo "✅ Passed: $TESTS_PASSED"
    echo "❌ Failed: $TESTS_FAILED"
    
    if [[ $TESTS_FAILED -gt 0 ]]; then
        echo ""
        echo "Failed tests:"
        for test in "${FAILED_TESTS[@]}"; do
            echo "  - $test"
        done
        echo ""
        echo "❌ Smoke tests FAILED"
        exit 1
    else
        echo ""
        echo "🎉 All smoke tests PASSED"
        exit 0
    fi
}

# Script entry point
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi