"""Generate simple SVG pet art for the Chore Pets MVP.

This script intentionally uses only the Python standard library so it can run on
any machine with Python 3. The committed PNG files remain the runtime assets for
the Echo Show UI; this script regenerates the SVG source art used as the master
assets for those pet states.
"""

from __future__ import annotations

from pathlib import Path


OUTPUT_DIR = Path(__file__).resolve().parent / "pets"
PET_TYPES = ("cat", "dog", "hamster", "panda")
STATES = {
    "thriving": {"face": "^ ^", "accent": "#f4b400", "message": "Thriving"},
    "happy": {"face": "^_^", "accent": "#34a853", "message": "Happy"},
    "neutral": {"face": "-_-", "accent": "#5f6368", "message": "Neutral"},
    "sad": {"face": "u_u", "accent": "#4285f4", "message": "Sad"},
    "sick": {"face": "x_x", "accent": "#db4437", "message": "Sick"},
}
PET_COLORS = {
    "cat": {"body": "#f4c28b", "ear": "#d48b5b"},
    "dog": {"body": "#c9955b", "ear": "#8c5a32"},
    "hamster": {"body": "#e5b77b", "ear": "#c98a5c"},
    "panda": {"body": "#f2f2f2", "ear": "#1f1f1f"},
}
PET_EMBLEMS = {
    "cat": "CAT",
    "dog": "DOG",
    "hamster": "HAM",
    "panda": "PAN",
}


def build_svg(pet_type: str, state: str) -> str:
    palette = PET_COLORS[pet_type]
    state_meta = STATES[state]
    emblem = PET_EMBLEMS[pet_type]

    return f"""<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512">
  <defs>
    <linearGradient id="bg-{pet_type}-{state}" x1="0%" x2="100%" y1="0%" y2="100%">
      <stop offset="0%" stop-color="#fff8ef" />
      <stop offset="100%" stop-color="{state_meta['accent']}" stop-opacity="0.35" />
    </linearGradient>
  </defs>
  <rect width="512" height="512" rx="48" fill="url(#bg-{pet_type}-{state})" />
  <circle cx="256" cy="220" r="132" fill="{palette['body']}" stroke="#2d2d2d" stroke-width="12" />
  <circle cx="160" cy="120" r="42" fill="{palette['ear']}" stroke="#2d2d2d" stroke-width="10" />
  <circle cx="352" cy="120" r="42" fill="{palette['ear']}" stroke="#2d2d2d" stroke-width="10" />
  <circle cx="214" cy="205" r="14" fill="#2d2d2d" />
  <circle cx="298" cy="205" r="14" fill="#2d2d2d" />
  <rect x="232" y="228" width="48" height="18" rx="9" fill="#2d2d2d" />
  <text x="256" y="292" text-anchor="middle" font-family="Verdana, sans-serif" font-size="34" font-weight="700" fill="#2d2d2d">{state_meta['face']}</text>
  <rect x="126" y="340" width="260" height="76" rx="24" fill="white" fill-opacity="0.88" />
  <text x="256" y="385" text-anchor="middle" font-family="Verdana, sans-serif" font-size="28" font-weight="700" fill="#2d2d2d">{emblem} - {state_meta['message']}</text>
  <circle cx="92" cy="420" r="22" fill="{state_meta['accent']}" />
  <circle cx="420" cy="92" r="18" fill="{state_meta['accent']}" fill-opacity="0.7" />
</svg>
"""


def main() -> None:
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    count = 0
    for pet_type in PET_TYPES:
        for state in STATES:
            path = OUTPUT_DIR / f"{pet_type}-{state}.svg"
            path.write_text(build_svg(pet_type, state), encoding="utf-8")
            count += 1
    print(f"Generated {count} SVG files in {OUTPUT_DIR}")


if __name__ == "__main__":
    main()
