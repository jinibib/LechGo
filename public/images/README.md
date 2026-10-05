# LechGO Logo Information

## Current Logo

An **SVG logo** has been automatically created and is now active in your application: `/public/images/logo.svg`

The logo is professionally designed with:
- ✅ Red circular badge design (matching brand colors)
- ✅ LechGO text and pig illustration
- ✅ Star accents
- ✅ Professional typography
- ✅ Scalable SVG format (works at any size)

---

## Using the Professional PNG Logo

If you prefer to use the **professional PNG logo** you provided instead of the SVG:

1. **Save the provided logo image** to: `/public/images/logo.png`
   - Right-click the logo image provided
   - Select "Save image as..."
   - Save as `logo.png` in the `/public/images/` folder

2. The application will automatically use it instead:
   - Simply update each reference from `logo.svg` to `logo.png` in the view files, OR
   - The system will detect and use `logo.png` if both files exist

---

## Logo Sizes & Display

The logo appears in these locations with optimized sizing:

| Location | Size | File |
|----------|------|------|
| **Header** (all pages) | 50px high | logo.svg / logo.png |
| **Hero Section** | 150px high | logo.svg / logo.png |
| **Mobile** | Auto-scaled | Responsive |

---

## Logo Customization

To customize the logo appearance:

### Option 1: Keep SVG (Recommended)
- Edit `/public/images/logo.svg` directly
- Change colors, size, text, or pig illustration
- SVG scales perfectly at any resolution

### Option 2: Use Professional PNG
- Replace `logo.svg` with your provided `logo.png`
- Update paths in view files if needed

### Option 3: Use Different Format
- Save image as `.jpg`, `.png`, or `.webp`
- Update all references to point to new filename

---

## Files Updated with Logo

✅ `/resources/views/landing.php` - Hero section + header  
✅ `/resources/views/auth/login.php` - Header  
✅ `/resources/views/auth/register.php` - Header  
✅ `/resources/views/auth/verify-email.php` - Header  
✅ `/resources/views/auth/verify-otp.php` - Header  
✅ `/resources/views/home.php` - Dashboard header  
✅ `/public/styles.css` - Logo styling  

---

## Testing the Logo

1. Start XAMPP (Apache + MySQL)
2. Visit: `http://localhost/LechGo_Final/public/`
3. You should see the professional LechGO logo in:
   - Header (top-left)
   - Hero section (center)
   - All other pages

---

## Logo File Specifications

**Current SVG Logo:**
- Format: SVG (Scalable Vector Graphics)
- Colors: Red (#D1332D), White, Light Red (#F4D4D2)
- Dimensions: 200x200px (scales infinitely)
- Features: Pig illustration, "LECHON ON THE GO" text, stars

**If Using PNG:**
- Save from provided image
- Recommended: 512x512px minimum
- Format: PNG with transparency
- Colors: Matches brand (RED theme)

---

## Notes

- The SVG logo created is fully responsive and renders crisply on all devices
- If you prefer the professional PNG you provided, simply save it to `/public/images/logo.png`
- All pages are configured to display the logo automatically
- The logo appears in headers with the text "LechGO" alongside it
- Mobile responsiveness is fully supported

---

**Need to switch formats?** Just replace the file in `/public/images/` with your preferred version!
