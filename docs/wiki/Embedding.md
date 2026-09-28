# Embedding on Websites and in Email

## WordPress

```text
[ecd_timer id="promotion"]
```

The shortcode creates a responsive `img` element and loads one shared script. When document visibility changes to `visible`, the images are fetched again with the `_t` parameter. There is no continuous server polling or new request for each frame.

## Email HTML

Copy **Link do mailingu (GIF)** (the email GIF link) from the admin panel and use it as the image's `src`. Example using a placeholder domain:

```html
<p>The promotion ends on December 31, 2027, at 23:59:59 in the Europe/Warsaw time zone.</p>
<img src="https://example.com/?ecd_action=render&amp;ecd=promotion&amp;mode=email"
     alt="Countdown to the end of the promotion"
     style="display:block;max-width:100%;height:auto;border:0;">
```

Your email editor must preserve the external URL. Importing the image into the editor's media library, attaching a file, or embedding it as an attachment may replace dynamic fetching with a previously generated copy. Do not paste a shortcode or JavaScript into an email.

## Image URL contract

| Parameter | Behavior |
|---|---|
| `ecd_action=render` | Activates the image endpoint |
| `ecd=ID` | Selects an existing configuration |
| `mode=email` or `mode=anim` | GIF: 60 frames with Imagick, a static GIF without Imagick |
| Any other or missing `mode` | PNG; WebP when GD supports it and the `Accept` header contains `image/webp` |
| `_t` | Browser refresh parameter; it does not create a separate plugin-side cache entry |

GET and HEAD are supported. HEAD does not generate an image and does not fully test codec functionality. The ID and configuration are not secrets: the endpoint is intended for public embedding. Do not put email addresses or recipient tokens in the URL.

A GIF is a finite 60-second sequence. Replaying it in an application does not automatically fetch a new value from the server. The shared cache uses 15-second buckets, so the first frame may come from an earlier request in the same bucket.

<a id="ograniczenia-klientow-pocztowych"></a>
<a id="email-client-limitations"></a>
## Email client limitations

Identical behavior across all clients or a fresh countdown on every opening cannot be guaranteed. Apple describes Mail Privacy Protection fetching remote content automatically in the background, regardless of recipient engagement. Google documents serving Gmail images through a secure proxy. Image blocking, prefetching, and animation handling remain outside the plugin's control.

Do not treat the timer as evidence of when a message was opened or as a mechanism for enforcing the offer deadline. Include a text deadline, make the first frame readable, and test the applications used by your recipients.

Sources: [Apple — Mail Privacy Protection](https://www.apple.com/legal/privacy/data/en/mail-privacy-protection/), [Google — image URL proxy](https://knowledge.workspace.google.com/admin/gmail/advanced/set-up-an-image-url-proxy-allowlist).
