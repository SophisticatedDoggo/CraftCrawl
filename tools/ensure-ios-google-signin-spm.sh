#!/usr/bin/env bash
set -euo pipefail

package_file="ios/App/CapApp-SPM/Package.swift"

if [ ! -f "$package_file" ]; then
  echo "Missing $package_file"
  exit 1
fi

if grep -q 'GoogleSignIn-iOS' "$package_file"; then
  exit 0
fi

perl -0pi -e 's/(\.package\(name: "OnesignalCapacitorPlugin", path: "\.\.\/\.\.\/\.\.\/node_modules\/\@onesignal\/capacitor-plugin"\))/$1,\n        .package(url: "https:\/\/github.com\/google\/GoogleSignIn-iOS", from: "8.0.0")/' "$package_file"

perl -0pi -e 's/(\.product\(name: "OnesignalCapacitorPlugin", package: "OnesignalCapacitorPlugin"\))/$1,\n                .product(name: "GoogleSignIn", package: "GoogleSignIn-iOS")/' "$package_file"

if ! grep -q 'GoogleSignIn-iOS' "$package_file"; then
  echo "Could not add GoogleSignIn-iOS to $package_file"
  exit 1
fi
