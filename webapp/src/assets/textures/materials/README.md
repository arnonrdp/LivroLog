# Shelf materials

Ten photorealistic raster materials generated with the built-in image_gen tool.
Prompts and original generation paths are recorded in generation.json.

Each material includes JPEGs prepared with sips from one generated compartment:

- preview.jpg: 1024 × 146, the complete compartment.
- shelfleft.jpg and shelfright.jpg: 48 × 146, fixed-width interior sides.
- shelfcenter.jpg: 928 × 146, the recessed back and horizontal shelf surfaces.

The three pieces repeat vertically every 146 px, matching the existing book rows.
Only the center stretches horizontally: repeating it would introduce seams in glass
reflections and stone veins. The original wood retains its existing 240/544/240
layout and repetition. Shading and reflections are baked into the JPGs, not CSS.
The left crop begins at x=1 because sips treats a zero crop offset as centered.

Book placement is calibrated per material in `src/config/shelfTextures.ts`.
`bookSupportY` is the bottom of the cover, just in front of the rear/floor junction
and behind the front edge. Shared CSS variables apply that position to both real
books and the settings preview, with a 24px top clearance. Covers use block layout
so inline-text baseline spacing cannot move their contact point. Hover scaling is
anchored at the bottom so books remain on the support surface.

The selected material is stored in `users.shelf_texture` through `PUT /auth/me`.
Profile responses include it, and shelf components receive the owner's material
explicitly. A visitor's own selection never overrides another owner's shelf.

The generated Open Graph sharing image follows the owner's material too. The
production API image only contains `api/`, so the three shelf JPEGs of each
material are also committed under `api/public/og/textures/<id>/`; copy a new
material there as well, otherwise its sharing image falls back to wood.
