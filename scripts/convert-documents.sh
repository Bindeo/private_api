#!/usr/bin/env bash

# Extract file components
path=$(dirname $1)
file=$(basename $1)
extension="${file##*.}"
filename="${file%.*}"
imgFolder="$path/$filename"

# Convert to pdf
if [ "$extension" != "pdf" ] && [ "$extension" != "PDF" ]; then
    soffice --headless --invisible --convert-to pdf --outdir ${path} $1
fi

# Make images folder
mkdir ${imgFolder}

# Create images
convert -density 150 -quality 100 -background white -alpha remove "$path/$filename.pdf" "$imgFolder/$filename%d.png"

# If original extension was not pdf, remove pdf created
if [ "$extension" != "pdf" ] && [ "$extension" != "PDF" ]; then
    rm "$path/$filename.pdf"
fi