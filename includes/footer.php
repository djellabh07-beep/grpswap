<?php
/**
 * includes/footer.php
 *
 * Closes what includes/header.php opened: the <main> region, <body>, and
 * <html>. Every protected page requires this once, after its own content,
 * as the very last thing in the file.
 */
?>
</main>

<noscript>
    <div class="fixed inset-0 z-[100] flex items-center justify-center bg-background p-6 text-center">
        <p class="font-arabic-body text-body-lg">
            <?= htmlspecialchars(t('app.name')) ?> يحتاج إلى JavaScript ليعمل بصورة صحيحة، بما في ذلك المساعد الصوتي.
        </p>
    </div>
</noscript>

</body>
</html>
