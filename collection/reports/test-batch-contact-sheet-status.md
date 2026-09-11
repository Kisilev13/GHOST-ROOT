# GHOST//ROOT — test-batch contact sheet

Real image sheets now exist. Labels are on the sheet only, never burned into
token PNGs.

| Sheet | Path |
| --- | --- |
| Full-size grid | `collection/reports/test-batch-contact-sheet.png` |
| Detail crops (eyes/collar/legendary/hardware) | `collection/reports/test-batch-detail-sheet.png` |
| 64 px circles | `collection/reports/test-batch-avatar-64.png` |
| Tokens | `collection/assets/test-batch/{id:04d}.png` |

Both grid sheets are regenerated deterministically by
`collection/scripts/build_review_sheets.py`. Labels live only on the sheet margins,
never on token art.

Visual verdict (Pass 4) is in `test-batch-visual-review.md`:
`REPEAT_VISUAL_PROTOTYPE_STAGE`.
