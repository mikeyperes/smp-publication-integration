# Asset loading

People chips reuse Core SearchBox assets only at rendered forms; no new global assets are enqueued. Chip decoration is scoped to `.hls` and omitted with `none`. Home, ordinary articles and unrelated admin screens gain no assets.

## Native author badge styles

The shared verification stylesheet is emitted once by the first decorated Post Info widget if it was not already printed in the page head. This includes ordinary pages with loop cards and editor previews, independently of page-level article output settings. Its native pair rules are scoped to `[data-smpi-native-author]`; they retain the saved verification size and margins. No frontend script or stylesheet file is added.
