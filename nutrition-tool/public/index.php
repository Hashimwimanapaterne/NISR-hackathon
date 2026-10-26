<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

session_start();
send_security_headers();

$english = [
    'language' => 'Language',
    'main_navigation' => 'Main navigation',
    'home' => 'Umurima Data home',
    'crop_advisor' => 'Agricultural Productivity Advisor',
    'admin_sign_in' => 'Admin sign in',
    'track' => 'Track 1 · Agricultural Productivity',
    'hero_title' => 'Plan crops.<br><em>Protect harvests.</em>',
    'hero_copy' => 'Explore district-level crop planning with soil stewardship, seasonal weather, harvest nutrition, and local market signals. Recommendations require sourced, district-specific data and local validation.',
    'demo_note' => 'Lookup catalogs are ready; source-backed measurements must be added before comparisons are available.',
    'explore_advisor' => 'Explore the Crop Advisor',
    'food_affordability' => 'Explore food affordability data',
    'supporting_lens' => 'Supporting food-access lens',
    'compare_food' => 'Compare food value and price',
    'food_intro' => 'Explore nutrient value per 100 RWF. Results are only as current and representative as each cited market observation and food-composition source.',
    'value_per_100' => 'Value per 100 RWF',
    'energy_macros' => 'Energy & macros',
    'vitamins_minerals' => 'Vitamins & minerals',
    'food_category' => 'Food category',
    'all_categories' => 'All categories',
    'find_food' => 'Find a food',
    'search_placeholder' => 'e.g. beans',
    'reset_filters' => 'Reset filters',
    'your_results' => 'Your results',
    'best_value_foods' => 'Best value foods',
    'loading_foods' => 'Loading foods…',
    'loading_latest' => 'Loading the latest available food and price data.',
    'ranked_caption' => 'Foods ranked by nutrient value per 100 RWF spent',
    'rank' => 'Rank',
    'food_nutrition_basis' => 'Food & nutrition basis',
    'market_price' => 'Market price',
    'cost_100g' => 'Cost / 100g',
    'nutrient_100rwf' => 'Nutrient / 100 RWF',
    'finding_foods' => 'Finding the best value foods…',
    'how_to_read' => 'How to read this:',
    'method_note' => 'Foods are compared by the amount of the selected nutrient you get for 100 RWF. Cost per 100g helps compare foods sold in different units. Nutrition values are per 100g edible portion.',
    'daily_intake' => 'Your daily intake',
    'track_today' => 'Track today’s food and nutrition',
    'meal_intro' => 'Log foods as you eat them. Macro flags use calorie-share ranges; low-intake and upper-limit alerts use general adult references.',
    'choose_food' => 'Choose food to add',
    'choose_food_option' => 'Choose a food…',
    'log_food' => 'Log food',
    'loading_profiles' => 'Loading nutrition profiles…',
    'meal_empty' => 'Log foods above to see today’s nutrient totals.',
    'daily_disclaimer' => 'The daily log is saved in this browser and resets each day. Red or amber flags are screening signals, not diagnoses; low intake matters when it persists, and one high day does not prove harm. Early-day totals are naturally incomplete. Macro ranges show the share of logged calories; upper limits are shown only for nutrients with established adult limits. Food data may omit supplements, and needs vary with age, health, pregnancy, and activity. Consult a qualified health professional for personal advice.',
    'hackathon_credit' => 'Built for the NISR Big Data Hackathon 2026.',
    'sample_prices' => 'Market comparisons require dated prices with a cited source.',
    'about_project' => 'About this project',
    'no_foods' => 'No foods available',
    'no_profiles' => 'No food nutrition profiles are available yet.',
    'food_unavailable' => 'Food data unavailable',
    'load_food_error' => 'Could not load food nutrition data. Please refresh to try again.',
    'restored_log' => 'Today’s food log restored. Adjust portions or add more foods; it resets tomorrow.',
    'start_logging' => 'Log foods as you eat them today. Default portions are 100 g and can be adjusted.',
    'restore_error' => 'Could not restore today’s saved food log. You can start a new log.',
    'save_error' => 'Today’s log could not be saved in this browser; it will be lost when you leave this page.',
    'new_day' => 'A new day has started. The daily food log has been reset.',
    'portion' => 'Portion',
    'remove' => 'Remove',
    'today_nutrition' => 'Today’s nutrition',
    'daily_total' => 'Daily total from',
    'logged_food' => 'logged food',
    'logged_foods' => 'logged foods',
    'calorie_share' => 'of logged calories',
    'daily_value' => 'daily value',
    'very_low_calorie_share' => 'Very low calorie share',
    'very_high_calorie_share' => 'Very high calorie share',
    'below_typical_range' => 'Below typical range',
    'above_typical_range' => 'Above typical range',
    'within_typical_range' => 'Within typical range',
    'log_calories' => 'Log calories to assess share',
    'very_low_energy' => 'Very low daily energy total',
    'below_energy_reference' => 'Below daily energy reference',
    'above_energy_reference' => 'Above daily energy reference',
    'near_energy_reference' => 'Near daily energy reference',
    'upper_limit_reached' => 'Conservative adult upper limit reached',
    'at_upper_limit' => 'At or above adult upper limit',
    'low_daily_total' => 'Low daily total',
    'below_daily_reference' => 'Below daily reference',
    'daily_reference_met' => 'Daily reference met',
    'try_again' => 'Try again',
    'no_matching_foods' => 'No matching foods',
    'search_hint' => 'Try another search, category, or nutrient to explore more foods.',
    'no_foods_match' => 'No foods match this selection yet.',
    'change_filters' => 'Change or clear your filters to see more foods.',
    'could_not_load' => 'Could not load foods',
    'temporarily_unavailable' => 'The food list is temporarily unavailable.',
    'data_error' => 'We could not reach the food data.',
    'connection_error' => 'Check your connection and try again.',
    'food_found' => 'food found',
    'foods_found' => 'foods found',
    'showing_value' => 'Showing',
    'value_suffix' => 'value',
    'based_on_price' => 'based on each food’s latest recorded market price. Check market, date, and source for local relevance.',
    'sample_warning' => 'Sample placeholder prices are not live observations and must not be used for purchasing or policy decisions.',
    'sample_source' => 'Sample placeholder (not observed)',
    'direct_observation' => 'Direct market observation',
    'public_dataset' => 'Public dataset',
    'source_unknown' => 'Source unknown',
    'food_label' => 'Food',
    'in_category' => ' in ',
    'matching' => ' matching ',
    'logged_calorie_share' => '% of logged calories',
    'nutrient_calories_kcal' => 'Calories',
    'nutrient_protein_g' => 'Protein',
    'nutrient_fat_g' => 'Fat',
    'nutrient_carbohydrates_g' => 'Carbohydrates',
    'nutrient_fiber_g' => 'Fiber',
    'nutrient_iron_mg' => 'Iron',
    'nutrient_zinc_mg' => 'Zinc',
    'nutrient_calcium_mg' => 'Calcium',
    'nutrient_potassium_mg' => 'Potassium',
    'nutrient_vitamin_a_ug' => 'Vitamin A',
    'nutrient_vitamin_c_mg' => 'Vitamin C',
    'nutrient_folate_ug' => 'Folate',
    'nutrient_vitamin_b12_ug' => 'Vitamin B12',
    'unit_portion' => 'daily value',
    'progress_label' => 'daily value',
    'intake_note_calories_kcal' => 'A sustained energy shortfall can cause fatigue and unintended weight loss; a sustained surplus can contribute to weight gain.',
    'intake_note_protein_g' => 'Persistently low protein can affect muscle maintenance and tissue repair. No upper limit is established for healthy adults.',
    'intake_note_fat_g' => 'Persistently very low fat can affect essential-fat intake and absorption of vitamins A, D, E and K. Total fat has no established upper limit.',
    'intake_note_carbohydrates_g' => 'A carbohydrate share outside the typical range is not a diagnosis; food quality and overall needs also matter.',
    'intake_note_fiber_g' => 'Low fiber over time can contribute to constipation. Increase fiber gradually and drink fluids.',
    'intake_note_iron_mg' => 'Low iron over time can lead to iron-deficiency anemia. High intake can be harmful; needs and limits vary by age and pregnancy.',
    'intake_note_zinc_mg' => 'Low zinc can affect immune function and wound healing; too much over time can interfere with copper absorption.',
    'intake_note_calcium_mg' => 'Persistently low calcium can affect bone health; high intake, especially from supplements, may increase kidney-stone risk.',
    'intake_note_potassium_mg' => 'High potassium can be dangerous for people with kidney disease or certain medicines; food needs are individual.',
    'intake_note_vitamin_a_ug' => 'Persistently low vitamin A can affect vision and immune function. The food data does not distinguish vitamin A forms for assessing excess.',
    'intake_note_vitamin_c_mg' => 'Very low vitamin C over time can cause scurvy; excessive intake can cause digestive symptoms.',
    'intake_note_folate_ug' => 'Low folate is a concern for blood health and pregnancy; high supplemental folic acid can mask vitamin B12 deficiency.',
    'intake_note_vitamin_b12_ug' => 'Persistently low vitamin B12 can cause anemia and nerve problems. No upper limit is established.',
    'of_reference' => 'of',
    'source_esoko' => 'e-Soko',
    'source_nisr' => 'NISR dataset',
    'food_data_label' => 'Food data',
];
$kinyarwanda = [
    'language' => 'Ururimi',
    'main_navigation' => 'Ibikubiyemo by’ingenzi',
    'home' => 'Ahabanza kuri Umurima Data',
    'crop_advisor' => 'Umujyanama ku musaruro w’ubuhinzi',
    'admin_sign_in' => 'Kwinjira k’umuyobozi',
    'track' => 'Gahunda ya 1 · Kongera umusaruro w’ubuhinzi',
    'hero_title' => 'Tegura ibihingwa.<br><em>Rinda umusaruro.</em>',
    'hero_copy' => 'Reba igenamigambi ry’ibihingwa ku rwego rw’akarere, imicungire irambye y’ubutaka, ikirere cy’ibihembwe, intungamubiri zo mu musaruro n’ibiciro byo ku masoko. Ibyifuzo bisaba amakuru afite inkomoko yizewe, yihariye akarere, no kugenzurwa n’inzobere zo mu karere.',
    'demo_note' => 'Urutonde rw’ibihingwa rurahari; hagomba kongerwamo ibipimo bifite inkomoko mbere yo kugereranya.',
    'explore_advisor' => 'Reba Umujyanama ku bihingwa',
    'food_affordability' => 'Reba amakuru ku bushobozi bwo kugura ibiribwa',
    'supporting_lens' => 'Isesengura ryunganira ibijyanye no kubona ibiribwa',
    'compare_food' => 'Gereranya agaciro k’ibiribwa n’ibiciro',
    'food_intro' => 'Reba ingano y’intungamubiri uhabwa kuri 100 RWF. Ibisubizo bishingira ku makuru aheruka kandi ahagarariye uko isoko rihagaze no ku masoko y’intungamubiri yavuzwe.',
    'value_per_100' => 'Agaciro kuri 100 RWF',
    'energy_macros' => 'Ingufu n’intungamubiri z’ibanze',
    'vitamins_minerals' => 'Vitamini n’imyunyungugu',
    'food_category' => 'Icyiciro cy’ibiribwa',
    'all_categories' => 'Ibyiciro byose',
    'find_food' => 'Shaka ikiribwa',
    'search_placeholder' => 'urugero: ibishyimbo',
    'reset_filters' => 'Siba ibyo wahisemo',
    'your_results' => 'Ibisubizo byawe',
    'best_value_foods' => 'Ibiribwa bifite agaciro keza',
    'loading_foods' => 'Ibiribwa birimo gutegurwa…',
    'loading_latest' => 'Turimo gushaka amakuru aheruka ku biribwa n’ibiciro.',
    'ranked_caption' => 'Ibiribwa bikurikirana hakurikijwe intungamubiri kuri 100 RWF yakoreshejwe',
    'rank' => 'Urutonde',
    'food_nutrition_basis' => 'Ibiribwa n’intungamubiri',
    'market_price' => 'Igiciro ku isoko',
    'cost_100g' => 'Igiciro / 100g',
    'nutrient_100rwf' => 'Intungamubiri / 100 RWF',
    'finding_foods' => 'Turimo gushaka ibiribwa bifite agaciro keza…',
    'how_to_read' => 'Uko wasoma ibi:',
    'method_note' => 'Ibiribwa bigereranywa hakurikijwe ingano y’intungamubiri wahabwa kuri 100 RWF. Igiciro kuri 100g gifasha kugereranya ibiribwa bigurishwa mu bipimo bitandukanye. Ingano y’intungamubiri ibarwa kuri 100g z’igice kiribwa.',
    'daily_intake' => 'Ibyo urya buri munsi',
    'track_today' => 'Kurikirana ibyo wariye n’intungamubiri uyu munsi',
    'meal_intro' => 'Andika ibiribwa uko ubiriye. Iburira ku ntungamubiri z’ibanze rishingira ku rugero rw’ingufu zitanga; imburi ku ngano nto cyangwa irenze ntarengwa ikoresha ingero rusange z’abantu bakuru.',
    'choose_food' => 'Hitamo ikiribwa wongeremo',
    'choose_food_option' => 'Hitamo ikiribwa…',
    'log_food' => 'Andika icyo wariye',
    'loading_profiles' => 'Turimo gupakira amakuru y’intungamubiri…',
    'meal_empty' => 'Andika ibiribwa wariye haruguru kugira ngo ubone intungamubiri zose uyu munsi.',
    'daily_disclaimer' => 'Ibyo wariye bibikwa muri iyi mushakisha kandi byongera gutangira buri munsi. Amabara atukura cyangwa asa n’icunga agaragaza gusa ibimenyetso byo kwitondera, si isuzuma ry’indwara; ingano nto iba ingenzi iyo imaze igihe, kandi umunsi umwe urimo byinshi ntugaragaza ko byangiza. Amakuru yo mu ntangiriro z’umunsi aba atuzuye bisanzwe. Ingano y’intungamubiri z’ibanze igaragaza uruhare rwazo mu ngufu z’ibyo wanditse; ingano ntarengwa igaragazwa gusa ku ntungamubiri zifite urugero ntarengwa ruzwi ku bantu bakuru. Amakuru y’ibiribwa ashobora kutagaragaza inyunganiramirire, kandi ibyo umuntu akenera biratandukana bitewe n’imyaka, ubuzima, gutwita n’imyitozo. Kugira inama ku buzima bwawe bwite, baza inzobere mu buzima.',
    'hackathon_credit' => 'Byateguwe mu marushanwa ya NISR Big Data Hackathon 2026.',
    'sample_prices' => 'Kugereranya ibiciro bisaba ibiciro byanditseho amatariki n’inkomoko yizewe.',
    'about_project' => 'Ibyerekeye uyu mushinga',
    'no_foods' => 'Nta biribwa bihari',
    'no_profiles' => 'Nta makuru y’intungamubiri z’ibiribwa arahari.',
    'food_unavailable' => 'Amakuru y’ibiribwa ntabonetse',
    'load_food_error' => 'Ntibyashobotse gupakira amakuru y’intungamubiri. Ongera ufungure urupapuro ugerageze.',
    'restored_log' => 'Ibyo wariye uyu munsi byagaruwe. Hindura ingano cyangwa wongere ibiribwa; byongera gutangira ejo.',
    'start_logging' => 'Andika ibiribwa uko ubiriye uyu munsi. Ingano isanzwe ni 100 g, ushobora kuyihindura.',
    'restore_error' => 'Ntibyashobotse kugarura ibyo wariye byabitswe. Ushobora gutangira bundi bushya.',
    'save_error' => 'Ntibyashobotse kubika ibyo wariye muri iyi mushakisha; bizasibangana uvuye kuri uru rupapuro.',
    'new_day' => 'Umunsi mushya watangiye. Ibyo wariye byasubijwe ku ntangiriro.',
    'portion' => 'Ingano',
    'remove' => 'Kuraho',
    'today_nutrition' => 'Intungamubiri z’uyu munsi',
    'daily_total' => 'Igiteranyo cy’umunsi ku biribwa',
    'logged_food' => 'wanditse',
    'logged_foods' => 'wanditse',
    'calorie_share' => 'by’ingufu wanditse',
    'daily_value' => 'by’umunsi',
    'very_low_calorie_share' => 'Ingufu zitangwa ni nke cyane',
    'very_high_calorie_share' => 'Ingufu zitangwa ni nyinshi cyane',
    'below_typical_range' => 'Munsi y’urugero rusanzwe',
    'above_typical_range' => 'Hejuru y’urugero rusanzwe',
    'within_typical_range' => 'Mu rugero rusanzwe',
    'log_calories' => 'Andika ingufu kugira ngo ugereranye',
    'very_low_energy' => 'Ingufu z’umunsi ni nke cyane',
    'below_energy_reference' => 'Munsi y’urugero fatizo rw’ingufu z’umunsi',
    'above_energy_reference' => 'Hejuru y’urugero fatizo rw’ingufu z’umunsi',
    'near_energy_reference' => 'Hafi y’urugero fatizo rw’ingufu z’umunsi',
    'upper_limit_reached' => 'Urugero rwo hejuru rw’abakuze rwagezweho',
    'at_upper_limit' => 'Ku rugero rwo hejuru rw’abakuze cyangwa kururenga',
    'low_daily_total' => 'Igiteranyo cy’umunsi ni gito',
    'below_daily_reference' => 'Munsi y’urugero fatizo rw’umunsi',
    'daily_reference_met' => 'Urugero fatizo rw’umunsi rwaragezweho',
    'of_reference' => 'kuri',
    'source_esoko' => 'e-Soko',
    'source_nisr' => 'Ububiko bw’amakuru bwa NISR',
    'food_data_label' => 'Amakuru y’ibiribwa',
    'try_again' => 'Ongera ugerageze',
    'no_matching_foods' => 'Nta biribwa bihuye',
    'search_hint' => 'Gerageza gushakisha ukundi, guhitamo ikindi cyiciro cyangwa intungamubiri.',
    'no_foods_match' => 'Nta biribwa bihuye n’ibi wahisemo kugeza ubu.',
    'change_filters' => 'Hindura cyangwa ukureho ibyo wahisemo kugira ngo ubone ibindi biribwa.',
    'could_not_load' => 'Ntibyashobotse gupakira ibiribwa',
    'temporarily_unavailable' => 'Urutonde rw’ibiribwa ntirubonetse by’akanya gato.',
    'data_error' => 'Ntitwashoboye kugera ku makuru y’ibiribwa.',
    'connection_error' => 'Reba aho uri kuri interineti wongere ugerageze.',
    'food_found' => 'ikiribwa cyabonetse',
    'foods_found' => 'ibiribwa byabonetse',
    'showing_value' => 'Hagaragazwa',
    'value_suffix' => 'agaciro',
    'based_on_price' => 'hashingiwe ku giciro giheruka kwandikwa kuri buri kiribwa. Reba isoko, itariki n’inkomoko y’amakuru kugira ngo umenye niba bikureba.',
    'sample_warning' => 'Ibiciro by’igerageza si ibyo ku isoko byapimwe; ntibigomba gukoreshwa mu kugura cyangwa mu gufata ibyemezo bya politiki.',
    'sample_source' => 'Igiciro cy’igerageza (kitapimwe)',
    'direct_observation' => 'Igiciro cyapimwe ku isoko',
    'public_dataset' => 'Ububiko bw’amakuru rusange',
    'source_unknown' => 'Inkomoko ntizwi',
    'food_label' => 'Ibiribwa',
    'in_category' => ' mu cyiciro cya ',
    'matching' => ' bihuye na ',
    'logged_calorie_share' => '% by’ingufu wanditse',
    'nutrient_calories_kcal' => 'Kalori',
    'nutrient_protein_g' => 'Poroteyine',
    'nutrient_fat_g' => 'Ibinure',
    'nutrient_carbohydrates_g' => 'Karubohidireti',
    'nutrient_fiber_g' => 'Fibure',
    'nutrient_iron_mg' => 'Ubutare',
    'nutrient_zinc_mg' => 'Zinki',
    'nutrient_calcium_mg' => 'Kalisiyumu',
    'nutrient_potassium_mg' => 'Potasiyumu',
    'nutrient_vitamin_a_ug' => 'Vitamini A',
    'nutrient_vitamin_c_mg' => 'Vitamini C',
    'nutrient_folate_ug' => 'Folati',
    'nutrient_vitamin_b12_ug' => 'Vitamini B12',
    'unit_portion' => 'by’umunsi',
    'progress_label' => 'by’umunsi',
    'intake_note_calories_kcal' => 'Kubura ingufu mu gihe kirekire bishobora gutera umunaniro no kunanuka utabigambiriye; kurya ingufu zirenze ibyo ukoresha bishobora kongera ibiro.',
    'intake_note_protein_g' => 'Kubura poroteyine mu gihe kirekire bishobora kugira ingaruka ku mitsi no gusana ingingo z’umubiri. Nta rugero ntarengwa rwashyizweho ku bantu bakuru bazima.',
    'intake_note_fat_g' => 'Kubura ibinure cyane mu gihe kirekire bishobora kugabanya ibinure by’ingenzi no kwinjiza vitamini A, D, E na K. Nta rugero ntarengwa rwashyizweho ku binure byose.',
    'intake_note_carbohydrates_g' => 'Ingano ya karubohidireti itari mu rugero rusanzwe si isuzuma ry’indwara; ubwiza bw’ibiribwa n’ibyo umubiri ukeneye na byo ni ingenzi.',
    'intake_note_fiber_g' => 'Kubura fibure mu gihe kirekire bishobora gutera impatwe. Yongera buhoro buhoro kandi unywe amazi.',
    'intake_note_iron_mg' => 'Kubura ubutare mu gihe kirekire bishobora gutera kubura amaraso. Ubutare bwinshi bushobora kwangiza; ibyo umuntu akenera biratandukana bitewe n’imyaka no gutwita.',
    'intake_note_zinc_mg' => 'Kubura zinki bishobora kugira ingaruka ku budahangarwa no gukira kw’ibikomere; nyinshi mu gihe kirekire ishobora kubangamira iyinjizwa rya muringa.',
    'intake_note_calcium_mg' => 'Kubura kalisiyumu mu gihe kirekire bishobora kugira ingaruka ku magufa; nyinshi, cyane cyane mu nyunganiramirire, ishobora kongera ibyago by’amabuye mu mpyiko.',
    'intake_note_potassium_mg' => 'Potasiyumu nyinshi ishobora guteza ibyago ku bafite indwara z’impyiko cyangwa bafata imiti imwe n’imwe; ibyo umuntu akenera biratandukanye.',
    'intake_note_vitamin_a_ug' => 'Kubura vitamini A mu gihe kirekire bishobora kugira ingaruka ku kureba no ku budahangarwa. Amakuru y’ibiribwa ntatandukanya amoko ya vitamini A kugira ngo harebwe iyarenze urugero.',
    'intake_note_vitamin_c_mg' => 'Kubura vitamini C cyane mu gihe kirekire bishobora gutera indwara ya skorubute; nyinshi cyane ishobora gutera ibibazo by’igogora.',
    'intake_note_folate_ug' => 'Kubura folati bireba ubuzima bw’amaraso no gutwita; aside folike nyinshi iva mu nyunganiramirire ishobora guhisha kubura vitamini B12.',
    'intake_note_vitamin_b12_ug' => 'Kubura vitamini B12 mu gihe kirekire bishobora gutera kubura amaraso n’ibibazo by’imyakura. Nta rugero ntarengwa rwashyizweho.',
];
$language = $_GET['lang'] ?? $_SESSION['nutrition_language'] ?? 'en';
if (!in_array($language, ['en', 'rw'], true)) {
    $language = $_SESSION['nutrition_language'] ?? 'en';
}
$_SESSION['nutrition_language'] = $language;
$messages = $language === 'rw' ? $kinyarwanda : $english;
$t = static fn (string $key): string => $messages[$key] ?? $english[$key] ?? $key;

$pdo = get_db_connection();
$categories = get_categories($pdo);
$nutrientGroups = get_nutrient_groups();
?>
<!DOCTYPE html>
<html lang="<?= h($language === 'rw' ? 'rw' : 'en') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#173d31">
    <meta name="description" content="<?= h($t('food_intro')) ?>">
    <title>Umurima Data — <?= h($language === 'rw' ? 'Rya neza ku giciro gito' : 'Eat well for less') ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=track1-7">
</head>
<body>

<header class="site-header">
    <nav class="topbar wrap" aria-label="<?= h($t('main_navigation')) ?>">
        <a class="brand" href="index.php" aria-label="<?= h($t('home')) ?>">
            <span class="brand-mark" aria-hidden="true">U</span>
            <span>umurima<span class="brand-light">data</span></span>
        </a>
        <a class="nav-link" href="crop_advisor.php"><?= h($t('crop_advisor')) ?> <span aria-hidden="true">↗</span></a>
        <a class="nav-link" href="nisr_data.php">Data sources <span aria-hidden="true">↗</span></a>
        <a class="nav-link" href="../admin/login.php"><?= h($t('admin_sign_in')) ?> <span aria-hidden="true">↗</span></a>
        <form class="language-control" action="index.php" method="get">
            <label for="language-select"><?= h($t('language')) ?> / Ururimi</label>
            <select id="language-select" name="lang">
                <option value="en" lang="en"<?= $language === 'en' ? ' selected' : '' ?>>English</option>
                <option value="rw" lang="rw"<?= $language === 'rw' ? ' selected' : '' ?>>Kinyarwanda</option>
            </select>
            <button type="submit"><?= h($language === 'rw' ? 'Hindura' : 'Apply') ?></button>
        </form>
    </nav>
    <div class="hero wrap">
        <p class="eyebrow"><span class="eyebrow-dot"></span> <?= h($t('track')) ?></p>
        <h1><?= $t('hero_title') ?></h1>
        <p class="hero-copy"><?= h($t('hero_copy')) ?></p>
        <div class="hero-meta">
            <span class="hero-meta-icon" aria-hidden="true">↗</span>
            <?= h($t('demo_note')) ?>
        </div>
        <a class="hero-cta" href="crop_advisor.php"><?= h($t('explore_advisor')) ?> <span aria-hidden="true">→</span></a>
        <a class="hero-secondary-link" href="#food-affordability"><?= h($t('food_affordability')) ?></a>
    </div>
</header>

<main class="wrap">
    <section class="explorer" id="food-affordability" aria-labelledby="explorer-title">
        <div class="section-heading">
            <div>
                <p class="section-kicker"><?= h($t('supporting_lens')) ?></p>
                <h2 id="explorer-title"><?= h($t('compare_food')) ?></h2>
                <p class="section-copy"><?= h($t('food_intro')) ?></p>
            </div>
            <span class="unit-badge"><?= h($t('value_per_100')) ?></span>
        </div>

        <div class="controls-block" id="controls-block">
            <?php foreach ($nutrientGroups as $groupName => $cols): ?>
                <fieldset class="nutrient-group">
                    <legend class="nutrient-group-label"><?= h($groupName === 'Energy & macros' ? $t('energy_macros') : $t('vitamins_minerals')) ?></legend>
                    <div class="nutrient-tabs" data-group>
                        <?php foreach ($cols as $col => $meta): ?>
                            <button type="button"
                                    data-nutrient="<?= h($col) ?>"
                                    aria-pressed="<?= $col === 'protein_g' ? 'true' : 'false' ?>"
                                    class="<?= $col === 'protein_g' ? 'active' : '' ?>">
                                <?= h($t('nutrient_' . $col)) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endforeach; ?>

            <div class="filter-row">
                <label class="filter-control" for="category-select">
                    <span><?= h($t('food_category')) ?></span>
                    <select id="category-select">
                        <option value=""><?= h($t('all_categories')) ?></option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= h($cat) ?>"><?= h($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="filter-control search-control" for="food-search">
                    <span><?= h($t('find_food')) ?></span>
                    <input id="food-search" type="search" maxlength="100" placeholder="<?= h($t('search_placeholder')) ?>" autocomplete="off">
                </label>
                <button class="reset-button" id="reset-filters" type="button"><?= h($t('reset_filters')) ?></button>
            </div>
        </div>

        <div class="results-heading">
            <div>
                <p class="section-kicker"><?= h($t('your_results')) ?></p>
                <h2 id="results-title"><?= h($t('best_value_foods')) ?></h2>
            </div>
            <div class="results-summary">
                <span class="status-dot" aria-hidden="true"></span>
                <span id="results-count" role="status" aria-live="polite"><?= h($t('loading_foods')) ?></span>
            </div>
        </div>

        <p class="result-note" id="result-note"><?= h($t('loading_latest')) ?></p>

        <div class="table-shell" id="table-shell">
            <table class="ledger">
                <caption class="sr-only"><?= h($t('ranked_caption')) ?></caption>
                <thead>
                    <tr>
                        <th class="rank-heading" scope="col"><?= h($t('rank')) ?></th>
                        <th scope="col"><?= h($t('food_nutrition_basis')) ?></th>
                        <th class="num" scope="col"><?= h($t('market_price')) ?></th>
                        <th class="num" scope="col"><?= h($t('cost_100g')) ?></th>
                        <th class="num nutrient-heading" id="nutrient-head-label" scope="col"><?= h($t('nutrient_100rwf')) ?></th>
                    </tr>
                </thead>
                <tbody id="results-body" aria-busy="true">
                    <tr><td colspan="5"><div class="loading-state"><span class="loader" aria-hidden="true"></span><?= h($t('finding_foods')) ?></div></td></tr>
                </tbody>
            </table>
        </div>

        <aside class="method-note">
            <span class="method-icon" aria-hidden="true">i</span>
            <p><strong><?= h($t('how_to_read')) ?></strong> <?= h($t('method_note')) ?></p>
        </aside>
    </section>

    <section class="meal-planner" aria-labelledby="meal-planner-title">
        <div class="meal-planner-heading">
            <div>
                <p class="section-kicker"><?= h($t('daily_intake')) ?></p>
                <h2 id="meal-planner-title"><?= h($t('track_today')) ?></h2>
                <p class="section-copy"><?= h($t('meal_intro')) ?></p>
            </div>
            <div class="meal-add-controls">
                <label class="sr-only" for="meal-food-select"><?= h($t('choose_food')) ?></label>
                <select id="meal-food-select" disabled>
                    <option value=""><?= h($t('loading_foods')) ?></option>
                </select>
                <button class="add-food-button" id="add-meal-food" type="button" disabled><?= h($t('log_food')) ?></button>
            </div>
        </div>
        <p class="meal-status" id="meal-status" role="status" aria-live="polite"><?= h($t('loading_profiles')) ?></p>
        <div id="meal-items" class="meal-items"></div>
        <div id="meal-nutrition" class="meal-nutrition" aria-live="polite">
            <p class="meal-empty"><?= h($t('meal_empty')) ?></p>
        </div>
        <p class="daily-value-note"><?= h($t('daily_disclaimer')) ?></p>
    </section>
</main>

<footer class="site-footer">
    <div class="footer-inner wrap">
        <a class="brand footer-brand" href="index.php"><span class="brand-mark" aria-hidden="true">U</span><span>umurima<span class="brand-light">data</span></span></a>
        <p><?= h($t('hackathon_credit')) ?><br><span><?= h($t('sample_prices')) ?></span></p>
        <a href="../README.md"><?= h($t('about_project')) ?> <span aria-hidden="true">↗</span></a>
    </div>
</footer>

<script>window.nutritionI18n = <?= json_encode(['language' => $language, 'messages' => $messages], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="assets/js/app.js?v=track1-7"></script>
</body>
</html>
