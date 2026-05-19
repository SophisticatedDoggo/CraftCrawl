#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SPLASH_STYLE="${CRAFTCRAWL_SPLASH_STYLE:-${CRAFTCRAWL_APP_ICON:-trail}}"

case "$SPLASH_STYLE" in
  trail-map)
    SPLASH_STYLE="trail"
    ;;
  trail|trail-dark|ember|ember-dark|riverstone|riverstone-dark|blackberry|blackberry-dark|barnwood|barnwood-dark)
    ;;
  *)
    echo "Unsupported CraftCrawl splash style '$SPLASH_STYLE'. Use trail, trail-dark, ember, ember-dark, riverstone, riverstone-dark, blackberry, blackberry-dark, barnwood, or barnwood-dark." >&2
    exit 1
    ;;
esac

SOURCE_IMAGE="$ROOT_DIR/images/craft-crawl-logo-${SPLASH_STYLE}_splash.png"

variants=(
  "trail:craft-crawl-logo-trail:splash"
  "trail-dark:craft-crawl-logo-trail-dark:splash_trail_dark"
  "ember:craft-crawl-logo-ember:splash_ember"
  "ember-dark:craft-crawl-logo-ember-dark:splash_ember_dark"
  "riverstone:craft-crawl-logo-riverstone:splash_riverstone"
  "riverstone-dark:craft-crawl-logo-riverstone-dark:splash_riverstone_dark"
  "blackberry:craft-crawl-logo-blackberry:splash_blackberry"
  "blackberry-dark:craft-crawl-logo-blackberry-dark:splash_blackberry_dark"
  "barnwood:craft-crawl-logo-barnwood:splash_barnwood"
  "barnwood-dark:craft-crawl-logo-barnwood-dark:splash_barnwood_dark"
)

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

for variant in "${variants[@]}"; do
  IFS=":" read -r _ image_name resource_name <<< "$variant"
  variant_source="$ROOT_DIR/images/${image_name}_splash.png"

  if [ ! -f "$variant_source" ]; then
    echo "Missing source splash image: $variant_source" >&2
    exit 1
  fi

  for android_size in "${android_sizes[@]}"; do
    IFS=":" read -r bucket size <<< "$android_size"
    output_dir="$ROOT_DIR/android/app/src/main/res/$bucket"
    mkdir -p "$output_dir"
    convert "$variant_source" -resize "${size}^" -gravity center -extent "$size" "$output_dir/${resource_name}.png"
  done
done

echo "Generated CraftCrawl iOS and Android splash assets."
