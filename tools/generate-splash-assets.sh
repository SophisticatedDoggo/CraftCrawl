#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE_IMAGE="$ROOT_DIR/images/craft-crawl-splash.png"

if ! command -v convert >/dev/null 2>&1; then
  echo "ImageMagick is required. Install it so the 'convert' command is available." >&2
  exit 1
fi

if [ ! -f "$SOURCE_IMAGE" ]; then
  echo "Missing source splash image: $SOURCE_IMAGE" >&2
  exit 1
fi

write_ios_contents() {
  local path="$1"

  cat > "$path" <<'JSON'
{
  "images" : [
    {
      "filename" : "splash-2732x2732.png",
      "idiom" : "universal",
      "scale" : "1x"
    },
    {
      "filename" : "splash-2732x2732-1.png",
      "idiom" : "universal",
      "scale" : "2x"
    },
    {
      "filename" : "splash-2732x2732-2.png",
      "idiom" : "universal",
      "scale" : "3x"
    }
  ],
  "info" : {
    "author" : "xcode",
    "version" : 1
  }
}
JSON
}

ios_dir="$ROOT_DIR/ios/App/App/Assets.xcassets/Splash.imageset"
mkdir -p "$ios_dir"
for ios_file in splash-2732x2732.png splash-2732x2732-1.png splash-2732x2732-2.png; do
  convert "$SOURCE_IMAGE" -resize 2732x2732 "$ios_dir/$ios_file"
done
write_ios_contents "$ios_dir/Contents.json"

android_sizes=(
  "drawable:480x320"
  "drawable-land-mdpi:480x320"
  "drawable-land-hdpi:800x480"
  "drawable-land-xhdpi:1280x720"
  "drawable-land-xxhdpi:1600x960"
  "drawable-land-xxxhdpi:1920x1280"
  "drawable-port-mdpi:320x480"
  "drawable-port-hdpi:480x800"
  "drawable-port-xhdpi:720x1280"
  "drawable-port-xxhdpi:960x1600"
  "drawable-port-xxxhdpi:1280x1920"
)

for android_size in "${android_sizes[@]}"; do
  IFS=":" read -r bucket size <<< "$android_size"
  output_dir="$ROOT_DIR/android/app/src/main/res/$bucket"
  mkdir -p "$output_dir"
  convert "$SOURCE_IMAGE" -resize "${size}^" -gravity center -extent "$size" "$output_dir/splash.png"
done

echo "Generated CraftCrawl iOS and Android splash assets."
