"""
dark_palette.py - color tables and helpers shared by the dark-mode tools.
Read-only helpers: this file never writes to project files.
"""
import os
import re

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))

SKIP_FILES = {os.path.normpath('includes/header.php')}
SKIP_PARTS = ('customer_portal',)
SKIP_NAME_RE = re.compile(r'(_old|backup|\.bak|_pdf\.php$)', re.I)

# Background tokens (light -> dark surface)
BG_MAP = {
    '#fff': '#232b2b', '#ffffff': '#232b2b', 'white': '#232b2b',
    '#f9fafb': '#353839', '#f8fafc': '#353839', '#f8f9fa': '#353839',
    '#f3f4f6': '#353839', '#f1f5f9': '#353839', '#f5f5f5': '#353839',
    '#e9ecef': '#3b444b', '#f9f9f9': '#353839',
    '#f0fdf4': 'rgba(34,197,94,.12)', '#ecfdf5': 'rgba(16,185,129,.14)',
    '#d1fae5': 'rgba(16,185,129,.18)', '#dcfce7': 'rgba(34,197,94,.18)',
    '#fef2f2': 'rgba(239,68,68,.12)', '#fee2e2': 'rgba(239,68,68,.18)',
    '#fef3c7': 'rgba(245,158,11,.18)', '#fffbeb': 'rgba(245,158,11,.12)',
    '#fff7ed': 'rgba(251,146,60,.14)', '#fffbf5': 'rgba(251,146,60,.08)',
    '#eff6ff': 'rgba(59,130,246,.14)', '#dbeafe': 'rgba(59,130,246,.18)',
    '#eef2ff': 'rgba(99,102,241,.14)', '#ede9fe': 'rgba(139,92,246,.18)',
    '#faf5ff': 'rgba(168,85,247,.12)', '#fdf4ff': 'rgba(168,85,247,.12)',
}

# Text tokens (dark text on light -> light text on dark)
TEXT_MAP = {
    '#000': '#e5e7eb', '#000000': '#e5e7eb', '#111': '#e5e7eb', '#111827': '#e5e7eb',
    '#1f2937': '#e5e7eb', '#1e293b': '#e5e7eb', '#0f172a': '#e5e7eb', '#333': '#e5e7eb',
    '#333333': '#e5e7eb', '#374151': '#d1d5db', '#4b5563': '#d1d5db', '#475569': '#d1d5db',
    '#6b7280': '#9ca3af', '#64748b': '#9ca3af', '#94a3b8': '#9ca3af',
}

# Border tokens
BORDER_MAP = {
    '#e5e7eb': '#414a4c', '#d1d5db': '#414a4c', '#e2e8f0': '#414a4c',
    '#cbd5e1': '#414a4c', '#f3f4f6': '#414a4c', '#f1f5f9': '#414a4c',
    '#e9ecef': '#414a4c',
}

DECL_RE = re.compile(r'(background(?:-color)?|color|border(?:-[a-z]+)?)\s*:\s*([^;"]+)', re.I)
HEX_RE = re.compile(r'#[0-9a-fA-F]{3,6}\b|\bwhite\b', re.I)
STYLE_ATTR_RE = re.compile(r'(\sstyle\s*=\s*)(["\'])(.*?)\2', re.S | re.I)


def should_skip(rel):
    if os.path.normpath(rel) in SKIP_FILES:
        return True
    if any(part in rel.replace('\\', '/').split('/') for part in SKIP_PARTS):
        return True
    return bool(SKIP_NAME_RE.search(os.path.basename(rel)))
