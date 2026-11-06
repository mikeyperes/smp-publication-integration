# Author photo visibility

Features > Authors and verification: **Hide author photo when the author has none** (`hide_author_photo_when_missing`), default on. Off preserves the original default portraits.

`smp_author_has_photo($user_id): bool` checks explicit profile images and avatar providers. Gravatar URLs are probed with `d=404`; definitive results are cached for one day. WordPress mystery-person and generated Gravatar defaults are not photos. Temporary transport failures preserve an unverified photo until the next request.

The output module removes the image and empty portrait-only links, widgets and columns. Author names, biographies, links and unrelated story images remain. It handles individually bound authors and the current post author, including Elementor loop output. Core avatars carry `data-smpi-author-id` so multi-author and comment output retain the correct identity. No new frontend assets are loaded; existing assets and admin asset scope remain unchanged.

Example before: `<div class="author"><div class="portrait"><img class="avatar" data-smpi-author-id="15"></div><div class="bio">Michael Peres</div></div>`.

After, for an author without a photo: `<div class="author"><div class="bio">Michael Peres</div></div>`.
