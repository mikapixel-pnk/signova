export async function clearPrivateAppCache():
  Promise<void> {
  if (
    typeof window ===
      "undefined" ||
    !("caches" in window)
  ) {
    return;
  }

  try {
    const keys =
      await window.caches.keys();

    await Promise.all(
      keys
        .filter(
          (key) =>
            key.startsWith(
              "signova-shell-",
            ) ||
            key.startsWith(
              "signova-runtime-",
            ),
        )
        .map(
          (key) =>
            window.caches.delete(
              key,
            ),
        ),
    );
  } catch {
    /*
     * Cache cleanup tidak boleh
     * menggagalkan logout server.
     */
  }
}
