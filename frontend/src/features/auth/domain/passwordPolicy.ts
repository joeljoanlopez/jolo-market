const MIN_LENGTH = 12;
const MAX_LENGTH = 128;

/**
 * PD-37, the same rules as the API's PlainPassword: 12 to 128 characters with an uppercase
 * letter, a lowercase letter, a digit and a character that is neither a letter nor a digit.
 * The API stays the authority; this lets a form explain the problem before sending.
 */
export function meetsPasswordPolicy(password: string): boolean {
  // Characters, not UTF-16 units: "ñ" or an emoji counts once, as in the API.
  const length = Array.from(password).length;
  return (
    length >= MIN_LENGTH &&
    length <= MAX_LENGTH &&
    /\p{Lu}/u.test(password) &&
    /\p{Ll}/u.test(password) &&
    /\p{Nd}/u.test(password) &&
    /[^\p{L}\p{N}]/u.test(password)
  );
}
