#!/bin/bash

# BinaryEcom20 API Testing Script
# This script runs Newman API tests locally or in CI/CD

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Default values
ENVIRONMENT="local"
REPORTER="cli"
VERBOSE=false

# Parse arguments
while [[ $# -gt 0 ]]; do
  case $1 in
    -e|--env)
      ENVIRONMENT="$2"
      shift 2
      ;;
    -r|--reporter)
      REPORTER="$2"
      shift 2
      ;;
    -v|--verbose)
      VERBOSE=true
      shift
      ;;
    -h|--help)
      echo "Usage: $0 [options]"
      echo "Options:"
      echo "  -e, --env ENVIRONMENT   Test environment (local, ci) [default: local]"
      echo "  -r, --reporter REPORTER Reporter type (cli, json, html) [default: cli]"
      echo "  -v, --verbose          Enable verbose output"
      echo "  -h, --help             Show this help message"
      exit 0
      ;;
    *)
      echo "Unknown option: $1"
      exit 1
      ;;
  esac
done

# Check if Newman is installed
if ! command -v newman &> /dev/null; then
  echo -e "${RED}Error: Newman is not installed${NC}"
  echo "Please install it with: npm install -g newman"
  exit 1
fi

# Check if collection exists
if [ ! -f "tests/postman/BinaryEcom20.postman_collection.json" ]; then
  echo -e "${RED}Error: Postman collection not found${NC}"
  echo "Expected location: tests/postman/BinaryEcom20.postman_collection.json"
  exit 1
fi

# Check if environment file exists
ENV_FILE="tests/postman/environments/${ENVIRONMENT}.json"
if [ ! -f "$ENV_FILE" ]; then
  echo -e "${RED}Error: Environment file not found${NC}"
  echo "Expected location: $ENV_FILE"
  exit 1
fi

echo -e "${GREEN}Running API Tests${NC}"
echo "Environment: $ENVIRONMENT"
echo "Reporter: $REPORTER"
echo "Collection: tests/postman/BinaryEcom20.postman_collection.json"
echo "Environment: $ENV_FILE"
echo ""

# Build Newman command
NEWMAN_CMD="newman run tests/postman/BinaryEcom20.postman_collection.json --environment $ENV_FILE --timeout-request 30000"

# Add reporter
case $REPORTER in
  cli)
    NEWMAN_CMD="$NEWMAN_CMD --reporters cli"
    ;;
  json)
    NEWMAN_CMD="$NEWMAN_CMD --reporters cli,json --reporter-json-export newman-report.json"
    ;;
  html)
    NEWMAN_CMD="$NEWMAN_CMD --reporters cli,html --reporter-html-export newman-report.html"
    ;;
  all)
    NEWMAN_CMD="$NEWMAN_CMD --reporters cli,json,html --reporter-json-export newman-report.json --reporter-html-export newman-report.html"
    ;;
esac

# Add verbose flag
if [ "$VERBOSE" = true ]; then
  NEWMAN_CMD="$NEWMAN_CMD --verbose"
fi

# Run tests
echo -e "${YELLOW}Executing: $NEWMAN_CMD${NC}"
echo ""

if eval $NEWMAN_CMD; then
  echo ""
  echo -e "${GREEN}✓ All API tests passed!${NC}"

  # Show report locations
  if [ "$REPORTER" = "json" ] || [ "$REPORTER" = "all" ]; then
    echo -e "${GREEN}📄 JSON report: newman-report.json${NC}"
  fi
  if [ "$REPORTER" = "html" ] || [ "$REPORTER" = "all" ]; then
    echo -e "${GREEN}📄 HTML report: newman-report.html${NC}"
  fi

  exit 0
else
  echo ""
  echo -e "${RED}✗ Some API tests failed${NC}"
  echo "Check the output above for details"

  if [ -f "newman-report.json" ]; then
    echo -e "${YELLOW}📄 Detailed report: newman-report.json${NC}"
  fi

  exit 1
fi
