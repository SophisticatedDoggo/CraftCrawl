#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if ! command -v convert >/dev/null 2>&1; then
  echo "ImageMagick is required. Install it so the 'convert' command is available." >&2
  exit 1
fi

variants=(
  "trail:craft-crawl-logo-trail:AppIcon-Trail:"
  "trail-dark:craft-crawl-logo-trail-dark:AppIcon-TrailDark:_trail_dark"
  "ember:craft-crawl-logo-ember:AppIcon-Ember:_ember"
  "ember-dark:craft-crawl-logo-ember-dark:AppIcon-EmberDark:_ember_dark"
)

densities=(
  "mdpi:48:108"
  "hdpi:72:162"
  "xhdpi:96:216"
  "xxhdpi:144:324"
  "xxxhdpi:192:432"
)

write_ios_contents() {
  local path="$1"

  cat > "$path" <<'JSON'
{
  "images" : [
    {
      "filename" : "AppIcon-512@2x.png",
      "idiom" : "universal",
      "platform" : "ios",
      "size" : "1024x1024"
    }
  ],
  "info" : {
    "author" : "xcode",
    "version" : 1
  }
}
JSON
}

write_android_adaptive_icon() {
  local path="$1"
  local foreground="$2"

  cat > "$path" <<XML
<adaptive-icon xmlns:android="http://schemas.android.com/apk/res/android">
    <background android:drawable="@color/ic_launcher_background"/>
    <foreground android:drawable="@mipmap/${foreground}"/>
</adaptive-icon>
XML
}

for variant in "${variants[@]}"; do
  IFS=":" read -r name image_name ios_set android_suffix <<< "$variant"
  source_image="$ROOT_DIR/images/${image_name}.png"

  if [ ! -f "$source_image" ]; then
    echo "Missing source icon: $source_image" >&2
    exit 1
  fi

  ios_dir="$ROOT_DIR/ios/App/App/Assets.xcassets/${ios_set}.appiconset"
  mkdir -p "$ios_dir"
  convert "$source_image" -resize 1024x1024 "$ios_dir/AppIcon-512@2x.png"
  write_ios_contents "$ios_dir/Contents.json"

  if [ "$name" = "trail" ]; then
    default_ios_dir="$ROOT_DIR/ios/App/App/Assets.xcassets/AppIcon.appiconset"
    mkdir -p "$default_ios_dir"
    convert "$source_image" -resize 1024x1024 "$default_ios_dir/AppIcon-512@2x.png"
    write_ios_contents "$default_ios_dir/Contents.json"
  fi

  android_base="ic_launcher${android_suffix}"
  for density in "${densities[@]}"; do
    IFS=":" read -r bucket icon_size foreground_size <<< "$density"
    mipmap_dir="$ROOT_DIR/android/app/src/main/res/mipmap-${bucket}"
    mkdir -p "$mipmap_dir"

    convert "$source_image" -resize "${icon_size}x${icon_size}" "$mipmap_dir/${android_base}.png"
    convert "$source_image" -resize "${icon_size}x${icon_size}" "$mipmap_dir/${android_base}_round.png"
    convert "$source_image" -resize "${foreground_size}x${foreground_size}" "$mipmap_dir/${android_base}_foreground.png"
  done

  if [ "$name" != "trail" ]; then
    adaptive_dir="$ROOT_DIR/android/app/src/main/res/mipmap-anydpi-v26"
    mkdir -p "$adaptive_dir"
    write_android_adaptive_icon "$adaptive_dir/${android_base}.xml" "${android_base}_foreground"
    write_android_adaptive_icon "$adaptive_dir/${android_base}_round.xml" "${android_base}_foreground"
  fi
done

echo "Generated CraftCrawl iOS and Android app icons."
