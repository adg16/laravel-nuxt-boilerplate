// Derives up to two uppercase initials from a person's name (first letter of the
// first two words), falling back to '?' for empty/blank names. Single-sourced so
// the avatar initials stay consistent wherever they're rendered.
export function getInitials(name?: string | null): string {
  const trimmed = name?.trim()
  if (!trimmed) return '?'
  const [first = '', second = ''] = trimmed.split(/\s+/)
  return ((first[0] ?? '') + (second[0] ?? '')).toUpperCase()
}
