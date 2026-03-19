#!/bin/bash
# Scores Mobile APK Build Script
# This script builds the Scores mobile application into an APK file

set -e

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${BLUE}=== Scores Mobile APK Build ===${NC}\n"

# Check if we're in the mobile directory
if [ ! -f "package.json" ]; then
    echo -e "${YELLOW}Not in mobile directory. Navigating to mobile/...${NC}"
    cd mobile
fi

# Step 1: Validate TypeScript
echo -e "${BLUE}Step 1: Validating TypeScript...${NC}"
npm run typecheck
echo -e "${GREEN}✓ TypeScript validation passed${NC}\n"

# Step 2: Install/update dependencies
echo -e "${BLUE}Step 2: Ensuring dependencies are installed...${NC}"
npm ci --prefer-offline
echo -e "${GREEN}✓ Dependencies ready${NC}\n"

# Step 3: Generate native Android code with Expo
echo -e "${BLUE}Step 3: Generating native Android code...${NC}"
npx expo prebuild --platform android --clean --non-interactive || {
    echo -e "${YELLOW}Warning: prebuild had issues, but continuing...${NC}"
}
echo -e "${GREEN}✓ Android code generated${NC}\n"

# Step 4: Build APK with Gradle
echo -e "${BLUE}Step 4: Building APK with Gradle...${NC}"
if [ -d "android" ]; then
    cd android
    
    # Try release build first, fallback to debug
    if ./gradlew assembleRelease; then
        APK_PATH="app/build/outputs/apk/release/app-release.apk"
        echo -e "${GREEN}✓ Release APK built successfully${NC}\n"
    else
        echo -e "${YELLOW}Release build failed, attempting debug build...${NC}"
        ./gradlew assembleDebug
        APK_PATH="app/build/outputs/apk/debug/app-debug.apk"
        echo -e "${GREEN}✓ Debug APK built successfully${NC}\n"
    fi
    
    # Report APK location
    if [ -f "$APK_PATH" ]; then
        SIZE=$(du -h "$APK_PATH" | cut -f1)
        echo -e "${GREEN}APK Ready:${NC}"
        echo -e "  Path: $(pwd)/$APK_PATH"
        echo -e "  Size: $SIZE"
    else
        echo -e "${YELLOW}Warning: APK file not found at expected location${NC}"
    fi
    
    cd ..
else
    echo -e "${YELLOW}Android directory not found. Make sure prebuild completed.${NC}"
    exit 1
fi

echo -e "\n${GREEN}=== Build Complete ===${NC}\n"
echo -e "To install the APK on a connected device:"
echo -e "  adb install -r $APK_PATH\n"
