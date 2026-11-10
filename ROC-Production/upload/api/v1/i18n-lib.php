<?php
/**
 * Run On Console — the "Can I run it?" game pages in other languages (Deploy 177).
 *
 * Owner, 2026-11-10: Search Console shows Germany, Brazil, Russia, France and Spain finding the English game pages
 * (Brazil at position 21). Competitors (sysrqmts.com) have the same pages in many languages. These are the same
 * data (official Steam requirements, the checker's rules) with the page text translated: /de/, /es/, /pt/, /fr/,
 * /ru/ + /can-i-run/<game>/. The Steam requirement lines stay as Steam writes them (English). Each page links its
 * other languages with hreflang, and the English page links them too once cli-languages.php has added the routes.
 * Rendered by game-i18n.php. Strings use sprintf placeholders (%1$s, %2$s ...).
 */

const ROC_I18N_LANGS = ['de', 'es', 'pt', 'fr', 'ru'];
const ROC_I18N_MARK = '# ROC i18n BEGIN';

/** True once cli-languages.php has added the routes (so no hreflang points to a page that is not there yet). */
function rocI18nOn(string $root): bool {
    static $on = null;
    if ($on === null) $on = strpos((string)@file_get_contents($root . '/.htaccess'), ROC_I18N_MARK) !== false;
    return $on;
}

function rocI18nPath(string $lang, string $enPath): string { return $lang === 'en' ? $enPath : '/' . $lang . $enPath; }

/** <link rel="alternate" hreflang> for one page in every language (+ x-default = English). */
function rocI18nAlternates(string $enPath): string {
    $h = '    <link rel="alternate" hreflang="en" href="' . ROC_PUBLIC_URL . $enPath . "\" />\n";
    foreach (ROC_I18N_LANGS as $l) $h .= '    <link rel="alternate" hreflang="' . $l . '" href="' . ROC_PUBLIC_URL . rocI18nPath($l, $enPath) . "\" />\n";
    return $h . '    <link rel="alternate" hreflang="x-default" href="' . ROC_PUBLIC_URL . $enPath . "\" />\n";
}

function rocI18n(string $lang): array {
    static $T = null;
    if ($T === null) $T = [
'de' => ['name' => 'Deutsch', 'home' => 'Startseite', 'cir' => 'Kann ich es spielen?',
    'title' => '%1$s Systemanforderungen (%2$s): Läuft es auf meinem PC?', 'title2' => '%1$s Systemanforderungen (%2$s)', 'title3' => '%1$s Systemanforderungen',
    'desc' => 'Läuft %1$s auf deinem PC? Minimum: %2$s. Die offiziellen Steam-Anforderungen und wie drei typische PCs abschneiden.',
    'desc2' => 'Läuft %1$s auf deinem PC? Die offiziellen Mindest- und empfohlenen Anforderungen von Steam.',
    'h1' => 'Kann ich %1$s spielen?', 'intro' => '%1$s: die offiziellen Mindest- und empfohlenen PC-Systemanforderungen von Steam, welche Grafikkarten und Prozessoren reichen und wie drei typische PCs abschneiden.',
    'soon' => 'Noch nicht erschienen. Release laut Steam: %1$s. Die Anforderungen können sich bis zum Start noch ändern.',
    'quick' => 'Kurze Antwort', 'min_gpu' => 'Minimale Grafikkarte: Klasse %1$s', 'rec_gpu' => 'Für hohe Einstellungen: Klasse %1$s', 'min_gpu_igpu' => 'Minimale Grafik: integrierte Grafik reicht',
    'ram_min' => 'Mindestens %1$s RAM', 'ram_rec' => '%1$s RAM empfohlen', 'size' => 'Download-Größe: %1$s',
    'pcs' => 'So laufen typische PCs', 'pc_laptop' => 'Laptop mit integrierter Grafik + 8 GB',
    't_high' => 'Hohe Einstellungen', 't_mid' => 'Mittlere bis hohe Einstellungen', 't_low' => 'Nur niedrige Einstellungen', 't_no' => 'Unter dem Minimum, nicht gut spielbar',
    'req' => '%1$s Systemanforderungen', 'part' => 'Komponente', 'min' => 'Minimum', 'rec' => 'Empfohlen',
    'l_os' => 'Betriebssystem', 'l_cpu' => 'Prozessor', 'l_ram' => 'Arbeitsspeicher', 'l_gpu' => 'Grafik', 'l_storage' => 'Speicherplatz', 'not_listed' => 'Nicht angegeben',
    'req_note' => 'Die Anforderungen stehen so, wie Steam sie angibt (auf Englisch).',
    'faq' => 'Fragen zu %1$s',
    'q_min' => 'Was sind die Mindestanforderungen für %1$s?', 'a_min' => 'Laut Steam: %1$s.',
    'q_rec' => 'Was sind die empfohlenen Anforderungen für %1$s?', 'a_rec' => 'Laut Steam: %1$s.',
    'q_gpu' => 'Welche Grafikkarte brauche ich für %1$s?', 'a_gpu' => 'Mindestens eine Karte der Klasse %1$s; für hohe Einstellungen eine der Klasse %2$s.', 'a_gpu1' => 'Mindestens eine Karte der Klasse %1$s.',
    'q_8' => 'Reichen 8 GB RAM für %1$s?', 'a_8no' => 'Nein. %1$s braucht mindestens %2$s RAM.', 'a_8min' => 'Ja, für niedrige Einstellungen; empfohlen sind %1$s.', 'a_8yes' => 'Ja. 8 GB erfüllen die Anforderungen.',
    'q_size' => 'Wie viele GB hat %1$s?', 'a_size' => '%1$s braucht %2$s freien Speicherplatz.',
    'q_igpu' => 'Läuft %1$s ohne Grafikkarte (integrierte Grafik)?', 'a_igpu_yes' => 'Ja, auf einem Laptop mit integrierter Grafik und 8 GB RAM sogar auf mittleren bis hohen Einstellungen.',
    'a_igpu_low' => 'Ja, aber nur mit niedrigen Einstellungen.', 'a_igpu_no' => 'Kaum: Die integrierte Grafik liegt unter dem Minimum. Eine eigene Grafikkarte ist nötig.',
    'full' => 'Mehr auf der englischen Seite', 'full_p' => 'Schnelltest für deinen PC, FPS-Berichte von Spielern, Steam Deck und PC-Builds:', 'full_btn' => 'Englische Seite öffnen',
    'langs' => 'Diese Seite in anderen Sprachen', 'similar' => 'Ähnliche Spiele', 'src' => 'Anforderungen: die offizielle Steam-Seite jedes Spiels, Stand %1$s.',
    'hub_title' => 'Kann ich es spielen? Anforderungen von %1$s PC-Spielen', 'hub_desc' => 'Offizielle Mindest- und empfohlene PC-Anforderungen von %1$s Spielen von Steam, mit Grafikkarten und Prozessoren, die sie schaffen.',
    'hub_h1' => 'Kann ich es spielen? %1$s PC-Spiele', 'hub_p' => 'Wähle ein Spiel: die offiziellen Anforderungen von Steam und wie typische PCs abschneiden.', 'pop' => 'Am beliebtesten', 'all' => 'Alle Spiele von A bis Z'],
'es' => ['name' => 'Español', 'home' => 'Inicio', 'cir' => '¿Puedo jugarlo?',
    'title' => 'Requisitos de %1$s (%2$s): ¿Lo puedo jugar en mi PC?', 'title2' => 'Requisitos de %1$s para PC (%2$s)', 'title3' => 'Requisitos de %1$s para PC',
    'desc' => '¿Tu PC puede con %1$s? Mínimo: %2$s. Requisitos oficiales de Steam y cómo rinden tres PC habituales.',
    'desc2' => '¿Tu PC puede con %1$s? Los requisitos mínimos y recomendados oficiales de Steam.',
    'h1' => '¿Puedo jugar %1$s?', 'intro' => '%1$s: los requisitos mínimos y recomendados oficiales de Steam para PC, qué tarjetas gráficas y procesadores lo mueven y cómo rinden tres PC habituales.',
    'soon' => 'Aún no ha salido. Lanzamiento en Steam: %1$s. Los requisitos pueden cambiar antes del lanzamiento.',
    'quick' => 'Respuesta rápida', 'min_gpu' => 'Tarjeta gráfica mínima: clase %1$s', 'rec_gpu' => 'Para calidad alta: clase %1$s', 'min_gpu_igpu' => 'Gráfica mínima: basta con gráficos integrados',
    'ram_min' => 'Mínimo %1$s de RAM', 'ram_rec' => '%1$s de RAM recomendados', 'size' => 'Tamaño de descarga: %1$s',
    'pcs' => 'Cómo rinden los PC habituales', 'pc_laptop' => 'Portátil con gráficos integrados + 8 GB',
    't_high' => 'Calidad alta', 't_mid' => 'Calidad media a alta', 't_low' => 'Solo calidad baja', 't_no' => 'Por debajo del mínimo, no se juega bien',
    'req' => 'Requisitos de %1$s', 'part' => 'Componente', 'min' => 'Mínimos', 'rec' => 'Recomendados',
    'l_os' => 'Sistema operativo', 'l_cpu' => 'Procesador', 'l_ram' => 'Memoria', 'l_gpu' => 'Gráficos', 'l_storage' => 'Almacenamiento', 'not_listed' => 'No indicado',
    'req_note' => 'Los requisitos aparecen tal como los publica Steam (en inglés).',
    'faq' => 'Preguntas sobre %1$s',
    'q_min' => '¿Cuáles son los requisitos mínimos de %1$s?', 'a_min' => 'Según Steam: %1$s.',
    'q_rec' => '¿Cuáles son los requisitos recomendados de %1$s?', 'a_rec' => 'Según Steam: %1$s.',
    'q_gpu' => '¿Qué tarjeta gráfica necesito para %1$s?', 'a_gpu' => 'Como mínimo una de la clase %1$s; para calidad alta, una de la clase %2$s.', 'a_gpu1' => 'Como mínimo una de la clase %1$s.',
    'q_8' => '¿Bastan 8 GB de RAM para %1$s?', 'a_8no' => 'No. %1$s necesita al menos %2$s de RAM.', 'a_8min' => 'Sí, para calidad baja; se recomiendan %1$s.', 'a_8yes' => 'Sí. 8 GB cumplen los requisitos.',
    'q_size' => '¿Cuántos GB ocupa %1$s?', 'a_size' => '%1$s necesita %2$s de espacio libre.',
    'q_igpu' => '¿Funciona %1$s sin tarjeta gráfica (gráficos integrados)?', 'a_igpu_yes' => 'Sí, en un portátil con gráficos integrados y 8 GB de RAM incluso con calidad media a alta.',
    'a_igpu_low' => 'Sí, pero solo con calidad baja.', 'a_igpu_no' => 'Casi no: los gráficos integrados quedan por debajo del mínimo. Hace falta una tarjeta gráfica.',
    'full' => 'Más en la página en inglés', 'full_p' => 'Prueba rápida de tu PC, FPS reales de jugadores, Steam Deck y configuraciones de PC:', 'full_btn' => 'Abrir la página en inglés',
    'langs' => 'Esta página en otros idiomas', 'similar' => 'Juegos parecidos', 'src' => 'Requisitos: la página oficial de Steam de cada juego, actualizada en %1$s.',
    'hub_title' => '¿Puedo jugarlo? Requisitos de %1$s juegos de PC', 'hub_desc' => 'Requisitos mínimos y recomendados oficiales de Steam de %1$s juegos de PC, con las tarjetas gráficas y procesadores que los mueven.',
    'hub_h1' => '¿Puedo jugarlo? %1$s juegos de PC', 'hub_p' => 'Elige un juego: sus requisitos oficiales de Steam y cómo rinden los PC habituales.', 'pop' => 'Los más populares', 'all' => 'Todos los juegos de la A a la Z'],
'pt' => ['name' => 'Português', 'home' => 'Início', 'cir' => 'Roda no meu PC?',
    'title' => 'Requisitos de %1$s (%2$s): Roda no meu PC?', 'title2' => 'Requisitos de %1$s para PC (%2$s)', 'title3' => 'Requisitos de %1$s para PC',
    'desc' => '%1$s roda no seu PC? Mínimo: %2$s. Requisitos oficiais da Steam e como três PCs comuns se saem.',
    'desc2' => '%1$s roda no seu PC? Os requisitos mínimos e recomendados oficiais da Steam.',
    'h1' => '%1$s roda no meu PC?', 'intro' => '%1$s: os requisitos mínimos e recomendados oficiais da Steam para PC, quais placas de vídeo e processadores rodam o jogo e como três PCs comuns se saem.',
    'soon' => 'Ainda não lançado. Lançamento na Steam: %1$s. Os requisitos podem mudar até o lançamento.',
    'quick' => 'Resposta rápida', 'min_gpu' => 'Placa de vídeo mínima: classe %1$s', 'rec_gpu' => 'Para gráficos altos: classe %1$s', 'min_gpu_igpu' => 'Vídeo mínimo: gráficos integrados bastam',
    'ram_min' => 'Mínimo de %1$s de RAM', 'ram_rec' => '%1$s de RAM recomendados', 'size' => 'Tamanho do download: %1$s',
    'pcs' => 'Como PCs comuns se saem', 'pc_laptop' => 'Notebook com gráficos integrados + 8 GB',
    't_high' => 'Gráficos altos', 't_mid' => 'Gráficos médios a altos', 't_low' => 'Só gráficos baixos', 't_no' => 'Abaixo do mínimo, não roda bem',
    'req' => 'Requisitos de %1$s', 'part' => 'Componente', 'min' => 'Mínimos', 'rec' => 'Recomendados',
    'l_os' => 'Sistema operacional', 'l_cpu' => 'Processador', 'l_ram' => 'Memória', 'l_gpu' => 'Placa de vídeo', 'l_storage' => 'Armazenamento', 'not_listed' => 'Não informado',
    'req_note' => 'Os requisitos aparecem como a Steam os publica (em inglês).',
    'faq' => 'Perguntas sobre %1$s',
    'q_min' => 'Quais são os requisitos mínimos de %1$s?', 'a_min' => 'Segundo a Steam: %1$s.',
    'q_rec' => 'Quais são os requisitos recomendados de %1$s?', 'a_rec' => 'Segundo a Steam: %1$s.',
    'q_gpu' => 'Qual placa de vídeo preciso para %1$s?', 'a_gpu' => 'No mínimo uma da classe %1$s; para gráficos altos, uma da classe %2$s.', 'a_gpu1' => 'No mínimo uma da classe %1$s.',
    'q_8' => '8 GB de RAM bastam para %1$s?', 'a_8no' => 'Não. %1$s precisa de pelo menos %2$s de RAM.', 'a_8min' => 'Sim, com gráficos baixos; o recomendado é %1$s.', 'a_8yes' => 'Sim. 8 GB atendem aos requisitos.',
    'q_size' => 'Quantos GB tem %1$s?', 'a_size' => '%1$s precisa de %2$s de espaço livre.',
    'q_igpu' => '%1$s roda sem placa de vídeo (gráficos integrados)?', 'a_igpu_yes' => 'Sim. Em um notebook com gráficos integrados e 8 GB de RAM roda até com gráficos médios a altos.',
    'a_igpu_low' => 'Sim, mas só com gráficos baixos.', 'a_igpu_no' => 'Quase não: os gráficos integrados ficam abaixo do mínimo. É preciso uma placa de vídeo.',
    'full' => 'Mais na página em inglês', 'full_p' => 'Teste rápido do seu PC, FPS reais de jogadores, Steam Deck e montagens de PC:', 'full_btn' => 'Abrir a página em inglês',
    'langs' => 'Esta página em outros idiomas', 'similar' => 'Jogos parecidos', 'src' => 'Requisitos: a página oficial de cada jogo na Steam, atualizada em %1$s.',
    'hub_title' => 'Roda no meu PC? Requisitos de %1$s jogos de PC', 'hub_desc' => 'Requisitos mínimos e recomendados oficiais da Steam de %1$s jogos de PC, com as placas de vídeo e processadores que rodam cada um.',
    'hub_h1' => 'Roda no meu PC? %1$s jogos de PC', 'hub_p' => 'Escolha um jogo: os requisitos oficiais da Steam e como PCs comuns se saem.', 'pop' => 'Mais populares', 'all' => 'Todos os jogos de A a Z'],
'fr' => ['name' => 'Français', 'home' => 'Accueil', 'cir' => 'Est-ce que ça tourne ?',
    'title' => 'Configuration requise de %1$s (%2$s) : ça tourne ?', 'title2' => 'Configuration requise de %1$s (%2$s)', 'title3' => 'Configuration requise de %1$s',
    'desc' => '%1$s tourne-t-il sur votre PC ? Minimum : %2$s. La configuration officielle de Steam et le résultat de trois PC courants.',
    'desc2' => '%1$s tourne-t-il sur votre PC ? La configuration minimale et recommandée officielle de Steam.',
    'h1' => '%1$s tourne-t-il sur mon PC ?', 'intro' => '%1$s : la configuration PC minimale et recommandée officielle de Steam, les cartes graphiques et processeurs qui le font tourner et le résultat de trois PC courants.',
    'soon' => 'Pas encore sorti. Sortie sur Steam : %1$s. La configuration peut changer avant la sortie.',
    'quick' => 'Réponse rapide', 'min_gpu' => 'Carte graphique minimale : classe %1$s', 'rec_gpu' => 'Pour les réglages élevés : classe %1$s', 'min_gpu_igpu' => 'Graphismes minimum : la puce graphique intégrée suffit',
    'ram_min' => '%1$s de RAM minimum', 'ram_rec' => '%1$s de RAM recommandés', 'size' => 'Taille du téléchargement : %1$s',
    'pcs' => 'Le résultat de PC courants', 'pc_laptop' => 'PC portable avec puce graphique intégrée + 8 Go',
    't_high' => 'Réglages élevés', 't_mid' => 'Réglages moyens à élevés', 't_low' => 'Réglages bas seulement', 't_no' => 'Sous le minimum, pas jouable correctement',
    'req' => 'Configuration requise de %1$s', 'part' => 'Composant', 'min' => 'Minimale', 'rec' => 'Recommandée',
    'l_os' => 'Système', 'l_cpu' => 'Processeur', 'l_ram' => 'Mémoire', 'l_gpu' => 'Graphismes', 'l_storage' => 'Stockage', 'not_listed' => 'Non indiqué',
    'req_note' => 'La configuration est affichée telle que Steam la publie (en anglais).',
    'faq' => 'Questions sur %1$s',
    'q_min' => 'Quelle est la configuration minimale de %1$s ?', 'a_min' => 'Selon Steam : %1$s.',
    'q_rec' => 'Quelle est la configuration recommandée de %1$s ?', 'a_rec' => 'Selon Steam : %1$s.',
    'q_gpu' => 'Quelle carte graphique faut-il pour %1$s ?', 'a_gpu' => 'Au moins une carte de classe %1$s ; pour les réglages élevés, une carte de classe %2$s.', 'a_gpu1' => 'Au moins une carte de classe %1$s.',
    'q_8' => '8 Go de RAM suffisent-ils pour %1$s ?', 'a_8no' => 'Non. %1$s demande au moins %2$s de RAM.', 'a_8min' => 'Oui, en réglages bas ; %1$s sont recommandés.', 'a_8yes' => 'Oui. 8 Go répondent à la configuration.',
    'q_size' => 'Combien de Go pèse %1$s ?', 'a_size' => '%1$s demande %2$s d\'espace libre.',
    'q_igpu' => '%1$s tourne-t-il sans carte graphique (puce intégrée) ?', 'a_igpu_yes' => 'Oui, sur un PC portable avec puce graphique intégrée et 8 Go de RAM, même en réglages moyens à élevés.',
    'a_igpu_low' => 'Oui, mais seulement en réglages bas.', 'a_igpu_no' => 'À peine : la puce intégrée est sous le minimum. Il faut une carte graphique.',
    'full' => 'Plus sur la page en anglais', 'full_p' => 'Test rapide de votre PC, FPS réels de joueurs, Steam Deck et configurations PC :', 'full_btn' => 'Ouvrir la page en anglais',
    'langs' => 'Cette page dans d\'autres langues', 'similar' => 'Jeux similaires', 'src' => 'Configuration : la page Steam officielle de chaque jeu, mise à jour en %1$s.',
    'hub_title' => 'Est-ce que ça tourne ? Configuration de %1$s jeux PC', 'hub_desc' => 'La configuration minimale et recommandée officielle de Steam de %1$s jeux PC, avec les cartes graphiques et processeurs qui les font tourner.',
    'hub_h1' => 'Est-ce que ça tourne ? %1$s jeux PC', 'hub_p' => 'Choisissez un jeu : sa configuration officielle de Steam et le résultat de PC courants.', 'pop' => 'Les plus populaires', 'all' => 'Tous les jeux de A à Z'],
'ru' => ['name' => 'Русский', 'home' => 'Главная', 'cir' => 'Потянет ли мой ПК?',
    'title' => 'Системные требования %1$s (%2$s): потянет ли мой ПК?', 'title2' => 'Системные требования %1$s (%2$s)', 'title3' => 'Системные требования %1$s',
    'desc' => 'Потянет ли ваш ПК %1$s? Минимум: %2$s. Официальные требования из Steam и результаты трёх типичных ПК.',
    'desc2' => 'Потянет ли ваш ПК %1$s? Официальные минимальные и рекомендуемые требования из Steam.',
    'h1' => 'Потянет ли мой ПК %1$s?', 'intro' => '%1$s: официальные минимальные и рекомендуемые системные требования из Steam, какие видеокарты и процессоры подходят и как справляются три типичных ПК.',
    'soon' => 'Игра ещё не вышла. Выход в Steam: %1$s. Требования могут измениться до релиза.',
    'quick' => 'Короткий ответ', 'min_gpu' => 'Минимальная видеокарта: класс %1$s', 'rec_gpu' => 'Для высоких настроек: класс %1$s', 'min_gpu_igpu' => 'Минимальная графика: хватит встроенной',
    'ram_min' => 'Минимум %1$s ОЗУ', 'ram_rec' => 'Рекомендуется %1$s ОЗУ', 'size' => 'Размер загрузки: %1$s',
    'pcs' => 'Как справляются типичные ПК', 'pc_laptop' => 'Ноутбук со встроенной графикой + 8 ГБ',
    't_high' => 'Высокие настройки', 't_mid' => 'Средние и высокие настройки', 't_low' => 'Только низкие настройки', 't_no' => 'Ниже минимума, играть некомфортно',
    'req' => 'Системные требования %1$s', 'part' => 'Компонент', 'min' => 'Минимальные', 'rec' => 'Рекомендуемые',
    'l_os' => 'ОС', 'l_cpu' => 'Процессор', 'l_ram' => 'Память', 'l_gpu' => 'Видеокарта', 'l_storage' => 'Место на диске', 'not_listed' => 'Не указано',
    'req_note' => 'Требования приведены так, как их публикует Steam (на английском).',
    'faq' => 'Вопросы о %1$s',
    'q_min' => 'Какие минимальные требования у %1$s?', 'a_min' => 'По данным Steam: %1$s.',
    'q_rec' => 'Какие рекомендуемые требования у %1$s?', 'a_rec' => 'По данным Steam: %1$s.',
    'q_gpu' => 'Какая видеокарта нужна для %1$s?', 'a_gpu' => 'Минимум карта класса %1$s; для высоких настроек — класса %2$s.', 'a_gpu1' => 'Минимум карта класса %1$s.',
    'q_8' => 'Хватит ли 8 ГБ ОЗУ для %1$s?', 'a_8no' => 'Нет. %1$s нужно минимум %2$s ОЗУ.', 'a_8min' => 'Да, на низких настройках; рекомендуется %1$s.', 'a_8yes' => 'Да. 8 ГБ соответствуют требованиям.',
    'q_size' => 'Сколько весит %1$s?', 'a_size' => 'Для %1$s нужно %2$s свободного места.',
    'q_igpu' => 'Пойдёт ли %1$s без видеокарты (на встроенной графике)?', 'a_igpu_yes' => 'Да, на ноутбуке со встроенной графикой и 8 ГБ ОЗУ даже на средних и высоких настройках.',
    'a_igpu_low' => 'Да, но только на низких настройках.', 'a_igpu_no' => 'Почти нет: встроенная графика ниже минимума. Нужна видеокарта.',
    'full' => 'Больше на английской странице', 'full_p' => 'Быстрая проверка вашего ПК, реальные FPS от игроков, Steam Deck и сборки ПК:', 'full_btn' => 'Открыть английскую страницу',
    'langs' => 'Эта страница на других языках', 'similar' => 'Похожие игры', 'src' => 'Требования: официальная страница каждой игры в Steam, обновлено: %1$s.',
    'hub_title' => 'Потянет ли мой ПК? Требования %1$s игр для ПК', 'hub_desc' => 'Официальные минимальные и рекомендуемые требования из Steam для %1$s игр для ПК и видеокарты и процессоры, которые с ними справляются.',
    'hub_h1' => 'Потянет ли мой ПК? %1$s игр для ПК', 'hub_p' => 'Выберите игру: официальные требования из Steam и результаты типичных ПК.', 'pop' => 'Самые популярные', 'all' => 'Все игры от A до Z'],
    ];
    return $T[$lang] ?? [];
}

function rocI18nT(array $L, string $key, ...$args): string { return isset($L[$key]) ? vsprintf($L[$key], $args) : ''; }
