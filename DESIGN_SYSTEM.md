# 🎨 Design System Reference

## Color Palette

### Primary Colors
| Name | Hex | RGB | Usage |
|------|-----|-----|-------|
| Primary Green | #4A9D6F | 74, 157, 111 | Buttons, links, highlights |
| Primary Dark | #2D6B4A | 45, 107, 74 | Hover states, dark text |
| Primary Light | #E8F5F0 | 232, 245, 240 | Backgrounds, light highlights |

### Accent Colors
| Name | Hex | RGB | Usage |
|------|-----|-----|-------|
| Accent Red | #E74C3C | 231, 76, 60 | Danger, alerts, errors |
| Accent Dark | #C0392B | 192, 57, 43 | Hover on accent |
| Accent Light | #FDEEF0 | 253, 238, 240 | Alert backgrounds |

### Secondary Colors
| Name | Hex | RGB | Usage |
|------|-----|-----|-------|
| Secondary Gold | #F4C430 | 244, 196, 48 | Highlights, special events |
| Secondary Dark | #D4A327 | 212, 163, 39 | Hover on secondary |
| Secondary Light | #FEF9E7 | 254, 249, 231 | Background highlights |

### Neutral Colors
| Name | Hex | RGB | Usage |
|------|-----|-----|-------|
| Background | #FAFAF8 | 250, 250, 248 | Main background |
| Card | #FFFFFF | 255, 255, 255 | Cards, modals |
| Card Subtle | #F5F3F1 | 245, 243, 241 | Secondary backgrounds |
| Border | #E8E8E8 | 232, 232, 232 | Borders, dividers |
| Border Subtle | #F0F0F0 | 240, 240, 240 | Subtle borders |

### Text Colors
| Name | Hex | RGB | Usage |
|------|-----|-----|-------|
| Text Primary | #2C2C2C | 44, 44, 44 | Main text |
| Text Secondary | #5A5A5A | 90, 90, 90 | Secondary text |
| Text Muted | #898989 | 137, 137, 137 | Disabled, hints |

### Specialty Colors
| Name | Hex | RGB | Usage |
|------|-----|-----|-------|
| Sky Blue | #5DADE2 | 93, 173, 226 | Information |
| Sky Light | #EAF2F8 | 234, 242, 248 | Info backgrounds |

## Typography

### Fonts
- **Headlines**: Outfit, sans-serif
  - Font weights: 600, 700
  - Sizes: 1.1rem, 1.3rem, 1.6rem

- **Body**: Plus Jakarta Sans, sans-serif
  - Font weights: 500, 600
  - Sizes: 0.85rem, 0.9rem, 0.92rem

### Font Sizes
| Element | Size |
|---------|------|
| H1 | 1.6rem (font-weight: 700) |
| H2 | 1.3rem (font-weight: 700) |
| H3/Card Title | 1.1rem (font-weight: 600) |
| Body | 0.92rem (font-weight: 400) |
| Small | 0.85rem (font-weight: 500) |
| Tiny | 0.74rem (font-weight: 600) |
| Label | 0.75rem (font-weight: 600) |

## Spacing Scale

Based on 8px grid:
| Size | Value |
|------|-------|
| xs | 4px |
| sm | 8px |
| md | 12px |
| lg | 16px |
| xl | 24px |
| 2xl | 32px |

## Border Radius

| Name | Value | Usage |
|------|-------|-------|
| sm | 6px | Small elements, badges |
| md | 10px | Buttons, inputs |
| lg | 14px | Cards, modals |
| xl | 20px | Large modals |
| full | 9999px | Pills, avatars |

## Shadows

### Minimalist Shadow System
```css
--shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.03);
--shadow-md: 0 2px 4px rgba(0, 0, 0, 0.05);
--shadow-lg: 0 4px 8px rgba(0, 0, 0, 0.08);
--shadow-xl: 0 8px 16px rgba(0, 0, 0, 0.1);
--shadow-hover: 0 8px 16px rgba(74, 157, 111, 0.12);
```

### Usage
- Elements at rest: shadow-sm
- Cards: shadow-sm
- Hover cards: shadow-md
- Modals: shadow-lg
- Buttons hover: shadow-sm (no large shadows)

## Component Styles

### Buttons
```
Primary Button:
- Background: #4A9D6F
- Text: White
- Padding: 10px 20px
- Border Radius: 10px
- Shadow: 0 2px 6px rgba(74, 157, 111, 0.2)
- Hover Shadow: 0 4px 10px rgba(74, 157, 111, 0.3)
```

### Cards
```
Card:
- Background: #FFFFFF
- Border: 1px solid #E8E8E8
- Border Radius: 14px
- Padding: 22px
- Shadow: shadow-sm
```

### Badges
```
Badge:
- Padding: 4px 10px
- Font Size: 0.74rem
- Font Weight: 600
- Border Radius: 9999px
- Text Transform: uppercase
```

### Forms
```
Input:
- Padding: 10px 12px
- Border: 1px solid #E8E8E8
- Border Radius: 10px
- Focus Border: #4A9D6F
- Focus Shadow: 0 0 0 2px #E8F5F0
```

## Transitions

| Type | Duration | Function |
|------|----------|----------|
| Fast | 0.15s | ease |
| Normal | 0.25s | ease |
| Bounce | 0.3s | ease |

## Icon System

Uses emoji for simplicity:
- 🏫 School/Building
- 🗓️ Calendar
- 📅 Date
- 🎈 Activity/Celebration
- 📊 Dashboard
- 👶 Children
- 👩‍🏫 Teacher
- 💬 Messages
- 📋 Attendance
- 🌟 Progress/Stars
- 🛡️ Security
- 💳 Payments
- 🔔 Notifications
- ✅ Approval
- ❌ Reject
- 🚨 Emergency

## Minimalist Design Principles Applied

1. **Reduction**: Remove unnecessary visual elements
2. **Clarity**: Make the most important information stand out
3. **Consistency**: Use the same patterns throughout
4. **Spacing**: Give elements room to breathe
5. **Simplicity**: Fewer colors, fewer shadows, fewer effects
6. **Flat Design**: Avoid 3D effects and complex gradients
7. **Typography**: Use size and weight for hierarchy
8. **Contrast**: Clear distinction between interactive and static elements

## Dark Mode Support (Future)

When implementing dark mode:
```css
--bg-main: #1A1A1A
--bg-card: #2D2D2D
--text-primary: #FFFFFF
--text-secondary: #B0B0B0
--border-color: #404040
```

## Accessibility

- All text maintains WCAG AA contrast ratios
- Minimum touch target size: 44px
- Focus states are visible
- No color-only information
- Proper semantic HTML

## Print Stylesheet

When printing:
- Hide navigation elements
- Use simplified colors
- Adjust spacing for paper
- Ensure good contrast

---

**Design System Version**: 1.0  
**Last Updated**: 2024-01-15  
**Maintenance**: Review quarterly for consistency
