var shareasaleWcTrackerPixelImg    = new Image();
shareasaleWcTrackerPixelImg.id     = shareasaleWcTrackerPixel.id;
shareasaleWcTrackerPixelImg.onload = shareasaleWcTrackerTriggered; //fn defined in shareasale-wc-tracker-triggered.js
shareasaleWcTrackerPixelImg.src    = shareasaleWcTrackerPixel.src;
shareasaleWcTrackerPixelImg.width  = 1;
shareasaleWcTrackerPixelImg.height = 1;
shareasaleWcTrackerPixelImg.setAttribute('data-no-lazy', 1);
document.body.appendChild(shareasaleWcTrackerPixelImg);