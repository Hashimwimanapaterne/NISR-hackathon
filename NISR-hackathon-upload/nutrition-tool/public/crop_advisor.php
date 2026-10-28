<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

session_start();
$language = $_GET['lang'] ?? $_SESSION['nutrition_language'] ?? 'en';
if (!in_array($language, ['en', 'rw'], true)) {
    $language = $_SESSION['nutrition_language'] ?? 'en';
}
$_SESSION['nutrition_language'] = $language;
$translations = [
    'en' => [
        'nutrition_tool' => 'Nutrition tool',
        'crop_advisor' => 'Crop advisor',
        'language' => 'Language',
        'apply' => 'Apply',
        'change' => 'Hindura',
        'page_title' => 'What should you plant, and where?',
        'intro' => 'Explore crop options for your district using NISR district-yield evidence, sourced food composition, recent market prices, public seasonal weather forecasts, and other documented inputs where available. Climate fit uses the public forecast and indicative crop ranges; missing soil and stewardship dimensions are excluded rather than estimated. Harvest time is informational and does not change rankings. Each score exposes its data source and limitations; results are not a substitute for local agronomic advice.',
        'soil_status' => 'Soil data status',
        'prototype_warning' => 'Data readiness:',
        'soil_warning' => 'NISR district yields, USDA FoodData Central composition profiles, and recent WFP Rwanda retail prices now support exploratory rankings where crop and district records match. WFP prices currently cover only five districts and selected foods. No cited district-specific soil suitability or stewardship scores are available, so those dimensions are excluded from the composite; USDA proxy matches and yield-to-food assumptions also limit precision. Verify local evidence before acting.',
        'sas_note' => 'Official crop-yield evidence imported from NISR’s',
        'sas_a_report_link' => 'Season A 2026 report',
        'sas_b_report_link' => 'Season B 2026 report',
        'sas_desc' => 'Season A Table 13 and Season B Table 11 district yields inform scoring for their matching season, district, and crop. For a crop without a usable NISR yield, the newest enabled public-source yield is used if available. Other report tables are preserved as reference observations. The reports do not contain crop maturity durations.',
        'explore_soilgrids' => 'Explore SoilGrids',
        'district' => 'District',
        'choose_district' => 'Choose your district…',
        'district_prompt' => 'Choose a district above to see ranked crop suggestions.',
        'footer' => 'Built for the NISR Big Data Hackathon 2026 · Rankings use only available cited dimensions; missing soil suitability and stewardship inputs are excluded. Treat results as exploratory and validate locally — see README.',
        'source_rwasis' => 'RwaSIS (RAB)',
        'source_soilgrids' => 'SoilGrids (ISRIC)',
        'source_nisr' => 'NISR dataset',
        'source_public_dataset' => 'Other public dataset',
        'source_illustrative' => 'Illustrative estimate',
        'market_unavailable' => 'Local price trend unavailable',
        'observation_dates' => 'observation dates',
        'neutral_score' => 'score shown as neutral',
        'local_observations_across' => 'local observations across',
        'dates_past_year' => 'dates in the past year',
        'date_unavailable' => 'date unavailable',
        'energy_macros' => 'Energy & macros',
        'minerals' => 'Minerals',
        'vitamins' => 'Vitamins',
        'nutrition_expected_harvest' => 'Nutrition from expected harvest',
        'tonnes_per_hectare' => 'tonnes/ha',
        'harvest_explanation' => 'Each amount below uses this crop’s full expected harvest and its linked food-composition profile. Score groups are balanced equally; nutrients with identical values across the compared crops do not affect the score.',
        'nutrient' => 'Nutrient',
        'estimated_harvest_total' => 'Estimated total in harvest',
        'yield_assumption' => 'Estimates assume the full expected yield has the linked profile’s edible composition. They do not account for edible-yield share, cooking, storage, processing, or field losses.',
        'loading' => 'Loading…',
        'load_error' => 'Could not load suggestions right now.',
        'load_error_retry' => 'Could not load suggestions right now. Please try again.',
        'no_suitability_for' => 'No suitability data yet for',
        'this_district' => 'this district',
        'expected_weather' => 'Expected growing-season weather',
        'score_crops_for' => 'Score crops for',
        'average_temperature' => 'average temperature',
        'expected_rainfall' => 'expected rainfall',
        'across_days' => 'across',
        'forecast_days' => 'forecast days',
        'season_window' => 'Season window',
        'to' => 'to',
        'model_covers' => 'Model covers',
        'weather_scoring_explanation' => 'Climate fit uses this public seasonal forecast compared with indicative crop temperature and rainfall ranges; no cited district climate baseline is currently available, so this is a provisional weather-based fit, not measured local crop suitability. Temperature contributes 60% and average weekly-equivalent rainfall 40% of the climate score. Local price trend compares date-averaged observations from the selected district over the past year; at least two distinct observation dates are required.',
        'forecast_source' => 'ECMWF SEAS5 ensemble-mean outlook from',
        'forecast_caution' => 'Seasonal forecasts are uncertain and updated monthly; use as planning guidance, not a planting guarantee.',
        'exploratory_comparison' => 'Exploratory comparison for',
        'ranking_caution' => 'Treat rankings as provisional; verify every input with local data and agronomic guidance before making planting decisions.',
        'score_weighting_note' => 'Base composite weights: {weights}. Weights are renormalized across included dimensions only. Observed yield and food-production priority adjustments are applied separately.',
        'score_excluded_dimensions' => 'Not scored because cited, complete district-specific data are unavailable:',
        'yield_season_note' => 'The ranking targets {season}. Each crop shows its actual yield survey year where available; historical yields are not a planting forecast.',
        'dimension_soil' => 'Soil fit',
        'dimension_climate' => 'Climate fit',
        'dimension_stewardship' => 'Soil stewardship',
        'soil_fit' => 'Soil fit',
        'climate_fit' => 'Climate fit',
        'seasonal_weather_fit' => 'Seasonal weather fit',
        'local_price_trend' => 'Local market price trend',
        'balanced_nutrition_ha' => 'Balanced nutrition/ha',
        'production_priority' => 'Food production priority',
        'farm_plan_heading' => 'Farm-to-food comparison',
        'farm_plan_intro' => 'Compare the three highest-ranked options using expected harvest, forecast fit, and estimated energy and protein per hectare. These are planning comparisons, not food-availability forecasts.',
        'plan_expected_harvest' => 'Expected harvest',
        'plan_weather_fit' => 'Weather fit',
        'plan_energy' => 'Energy / ha',
        'plan_protein' => 'Protein / ha',
        'plan_evidence' => 'Yield and food-profile basis',
        'plan_evidence_limit' => 'Nutrition totals assume all harvested mass has the linked food profile; edible share, losses, storage, processing, and preparation are not modeled.',
        'evidence_quality_heading' => 'Evidence and limitations',
        'evidence_quality_intro' => 'Evidence checklist—not a statistical confidence score.',
        'evidence_yield' => 'Yield',
        'evidence_nutrition' => 'Food composition',
        'evidence_market' => 'Market history',
        'evidence_weather' => 'Weather',
        'evidence_soil' => 'Soil and stewardship',
        'yield_record_source' => 'Observed district yield from {publisher}, {years}. Historical, not a forecast.',
        'yield_assumption_source' => 'No matching observed district yield; ranking uses the crop yield assumption. Do not interpret it as an observed district result.',
        'food_profile_proxy' => 'Mapped food-profile proxy: {food}; verify this local food form.',
        'food_label_match' => 'Catalog food label matches; local variety and preparation are not verified.',
        'open_fdc_record' => 'Open USDA FoodData Central record',
        'food_source_missing' => 'No traceable food-composition citation is available.',
        'market_quality_note' => '{dates} dated observations from {first} to {latest}. This is a price trend, not profitability; cost of production and market access are not included.',
        'market_quality_missing' => 'Fewer than two observation dates; market trend is unavailable and its score is neutral.',
        'weather_quality_note' => 'Open-Meteo forecast retrieved {date} covers {coverage}% of the selected season window. Weather fit uses indicative crop ranges, not a measured district suitability baseline.',
        'soil_quality_missing' => 'No cited district-specific soil suitability or stewardship scores; these dimensions are excluded.',
        'district_chart_title' => 'District yield comparison',
        'district_chart_intro' => 'Compare official NISR published yields across Rwanda’s districts for Seasons A and B. Missing observations are shown as not reported, not zero.',
        'district_chart_crop' => 'Crop',
        'district_chart_scale' => 'Bars use a shared scale within this crop; maximum reported yield is {maximum} kg/ha.',
        'district_chart_a' => 'Season A',
        'district_chart_b' => 'Season B',
        'district_chart_missing' => 'Not reported',
        'district_chart_error' => 'District yield comparison is temporarily unavailable.',
        'district_chart_source' => 'Source: NISR Seasonal Agricultural Survey, Season A Table 13 and Season B Table 11, 2026. The visual shows only published district yield observations; unreported district/crop values are not inferred.',
        'production_priority_note' => 'Production priority contributes 20% of the final score: grains, tubers, and legumes receive high priority; vegetables are down-ranked for large-scale planting. This planning preference does not change their nutrient values or soil/climate fit.',
        'observed_yield_score' => 'Observed district yield',
        'observed_yield_scoring_note' => 'NISR is preferred where it has a matching district/crop/season yield. Otherwise, the newest enabled public dataset with a matching observation is used. Selected observed yield contributes 40% before the 20% production-priority factor (32% of the final score when present). This is historical evidence, not a forecast or planting recommendation.',
        'public_yield_source' => 'Public source',
        'soil_stewardship' => 'Soil stewardship',
        'rank' => 'Rank',
        'risk_low' => 'low',
        'risk_medium' => 'medium',
        'risk_high' => 'high',
        'erosion_risk' => 'erosion risk',
        'expected_harvest' => 'Expected harvest',
        'harvest_time' => 'Planting to first harvest',
        'harvest_time_unknown' => 'Harvest time not recorded',
        'harvest_time_note' => 'Indicative general range, not an NISR measurement; variety, altitude, planting material, and management affect timing. This information does not change the ranking.',
        'days' => 'days',
        'yield_evidence' => 'Yield evidence',
        'yield_illustrative' => 'Yield estimate source is not recorded.',
        'soil_data' => 'Soil data',
        'record_updated' => 'Suitability record updated',
        'soil_indicators' => 'Soil protection and recovery indicators',
        'erosion_protection' => 'Erosion protection',
        'nutrient_balance' => 'Nutrient balance / lower depletion potential',
        'soil_structure' => 'Soil structure and organic matter potential',
        'stewardship_guidance' => 'Protect soil with locally suitable ground cover, balanced nutrient management, and practices that maintain soil structure.',
        'suggested_rotation' => 'Suggested rotation',
        'rotation_guidance' => 'Alternate crop families and include a locally suitable legume or cover crop where agronomically appropriate.',
        'provisional_guidance' => 'These scores and rotations are provisional guidance; soil effects depend on local conditions and management.',
        'api_error' => 'Could not load crop suggestions right now. Please try again.',
        'no_suitability' => 'No crops with usable nutrient profiles and yield evidence are available for this district and season yet.',
        'nutrient_calories_kcal' => 'Energy',
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
    ],
    'rw' => [
        'nutrition_tool' => 'igipimo cyimirire',
        'crop_advisor' => 'Ubujyanama ku bihingwa',
        'language' => 'Ururimi',
        'apply' => 'Hindura',
        'change' => 'Hindura',
        'page_title' => 'Ni iki wakwiriye guhinga, kandi he?',
        'intro' => 'Reba ibihingwa ukurikije umusaruro wa NISR wapimwe mu karere, intungamubiri zavuye mu makuru yizewe, ibiciro biheruka, iteganyagihe rusange ry’ibihe by’ihinga n’andi makuru yanditse aho aboneka. Ikirere gipimirwa ku iteganyagihe rusange n’ingano z’ikigereranyo z’ibihingwa; amanota y’ubutaka no kubungabunga ubutaka bitaboneka ntabarwa aho kuyahimba. Igihe cyo gusarura ni amakuru yo kureberaho kandi ntigihindura urutonde. Amanota agaragaza inkomoko n’aho agarukira; ntasimbura inama z’inzobere mu buhinzi zo mu karere.',
        'soil_status' => 'Imiterere y’amakuru y’ubutaka',
        'prototype_warning' => 'Imiterere y’amakuru:',
        'soil_warning' => 'Umusaruro wa NISR wapimwe mu turere, amakuru y’intungamubiri ya USDA FoodData Central n’ibiciro biheruka bya WFP mu Rwanda ubu bifasha gutanga urutonde rw’ibanze aho amakuru ahuye n’igihingwa n’akarere. Ibiciro bya WFP biboneka mu turere dutanu no ku biribwa bimwe gusa. Nta manota y’ubutaka cyangwa kubungabunga ubutaka yanditse kandi yihariye akarere ahari, bityo ntabarwa; amakuru asa n’ibiribwa bya USDA n’ihuzwa ry’umusaruro n’ibiribwa na byo bigabanya ukuri. Banza ugenzure amakuru yo mu karere.',
        'sas_note' => 'Amakuru yemewe y’umusaruro w’ibihingwa yinjijwe avuye muri raporo ya NISR:',
        'sas_a_report_link' => 'Raporo y’igihembwe A 2026',
        'sas_b_report_link' => 'Raporo y’igihembwe B 2026',
        'sas_desc' => 'Umusaruro wo mu mbonerahamwe ya 13 y’igihembwe A n’iya 11 y’igihembwe B ukoreshwa mu gihembwe gihuye n’akarere n’igihingwa. Ku gihingwa kidafite umusaruro wa NISR ushobora gukoreshwa, hakoreshwa amakuru mashya abitswe aturutse mu bundi bubiko rusange iyo ahari. Izindi mbonerahamwe zibitswe nk’amakuru yo kureberaho. Raporo ntizigaragaza iminsi kuva igihingwa gitewe kugeza gisaruwe.',
        'explore_soilgrids' => 'Reba SoilGrids',
        'district' => 'Akarere',
        'choose_district' => 'Hitamo akarere…',
        'district_prompt' => 'Hitamo akarere haruguru kugira ngo ubone urutonde rw’ibihingwa byasabitswe.',
        'footer' => 'Byateguwe mu marushanwa ya NISR Big Data Hackathon 2026 · Urutonde rukoresha gusa amakuru yizewe aboneka; amanota y’ubutaka no kubungabunga ubutaka bitaboneka ntabarwa. Genzura ibyavuye mu karere — reba README.',
        'source_rwasis' => 'RwaSIS (RAB)',
        'source_soilgrids' => 'SoilGrids (ISRIC)',
        'source_nisr' => 'Ububiko bw’amakuru bwa NISR',
        'source_public_dataset' => 'Ubundi bubiko rusange bw’amakuru',
        'source_illustrative' => 'Ikigereranyo cy’icyitegererezo',
        'market_unavailable' => 'Ihindagurika ry’ibiciro byo mu karere ntiriboneka',
        'observation_dates' => 'amatariki yapimweho',
        'neutral_score' => 'amanota agaragazwa nk’atabogamye',
        'local_observations_across' => 'ibipimo byo mu karere mu minsi',
        'dates_past_year' => 'mu mwaka ushize',
        'date_unavailable' => 'itariki ntiboneka',
        'energy_macros' => 'Ingufu n’intungamubiri z’ibanze',
        'minerals' => 'Imyunyungugu',
        'vitamins' => 'Vitamini',
        'nutrition_expected_harvest' => 'Intungamubiri ziva ku musaruro uteganyijwe',
        'tonnes_per_hectare' => 'toni/ha',
        'harvest_explanation' => 'Ingano zigaragazwa zikoresha umusaruro wose uteganyijwe w’iki gihingwa n’amakuru y’intungamubiri z’ibiribwa bifitanye isano. Amatsinda ahabwa amanota angana; intungamubiri zingana mu bihingwa bigereranywa ntizihindura amanota.',
        'nutrient' => 'Intungamubiri',
        'estimated_harvest_total' => 'Igiteranyo kigereranijwe mu musaruro',
        'yield_assumption' => 'Igereranya rifata ko umusaruro wose uteganyijwe ufite imiterere y’igice kiribwa igaragazwa n’amakuru ajyanye na wo. Ntiribariramo igice kiribwa, guteka, kubika, gutunganya cyangwa igihombo cyo mu murima.',
        'loading' => 'Birimo gupakira…',
        'load_error' => 'Ntibyashobotse kubona ibyifuzo by’ibihingwa ubu.',
        'load_error_retry' => 'Ntibyashobotse kubona ibyifuzo by’ibihingwa ubu. Ongera ugerageze.',
        'no_suitability_for' => 'Nta makuru y’ubukwiriye arahari ku karere ka',
        'this_district' => 'aka karere',
        'expected_weather' => 'Ikirere giteganyijwe mu gihe cy’ihinga',
        'score_crops_for' => 'Kubara amanota y’ibihingwa mu',
        'average_temperature' => 'impuzandengo y’ubushyuhe',
        'expected_rainfall' => 'imvura iteganyijwe',
        'across_days' => 'mu minsi',
        'forecast_days' => 'y’iteganyagihe',
        'season_window' => 'Igihe cy’ihinga',
        'to' => 'kugeza',
        'model_covers' => 'Iteganyagihe rikubiyemo',
        'weather_scoring_explanation' => 'Amanota y’ikirere akoresha iri teganyagihe rusange akarigereranya n’ingano z’ikigereranyo z’ubushyuhe n’imvura bikenewe n’ibihingwa; ubu nta mpuzandengo y’ikirere y’akarere yanditse kandi yizewe ihari, bityo aya ni amanota y’agateganyo ashingiye ku iteganyagihe, si igipimo cy’uko igihingwa kibereye ako karere. Ubushyuhe bugira uruhare rwa 60%, imvura ihwanye n’iy’icyumweru ikagira 40%. Ihindagurika ry’ibiciro rigereranya ibipimo byo mu karere katoranyijwe mu mwaka ushize; hakenewe nibura amatariki abiri atandukanye.',
        'forecast_source' => 'Iteganyagihe ry’impuzandengo y’itsinda rya ECMWF SEAS5 riboneka kuri',
        'forecast_caution' => 'Iteganyagihe ry’ibihembwe ntiriba ryizewe neza kandi rivugururwa buri kwezi; rifate nk’amakuru afasha igenamigambi, si icyemezo cy’uko igihingwa kizera.',
        'exploratory_comparison' => 'Igereranya ry’ibanze ry’akarere ka',
        'ranking_caution' => 'Urutonde ni urw’agateganyo; genzura amakuru yose ukoresheje amakuru y’aho n’inama z’inzobere mu buhinzi mbere yo gufata icyemezo cyo guhinga.',
        'score_weighting_note' => 'Ibipimo by’amanota fatizo: {weights}. Ibipimo bigabanywa hakurikijwe amakuru ariho gusa. Umusaruro wapimwe n’icyiciro cy’ibiribwa bishyirwa imbere bibarwa ukwabyo.',
        'score_excluded_dimensions' => 'Ntibyashyizwe mu manota kuko amakuru yuzuye kandi yizewe y’akarere atabonetse:',
        'yield_season_note' => 'Urutonde rushingiye ku gihembwe cya {season}. Buri gihingwa kigaragaza umwaka wapimweho umusaruro aho uhari; umusaruro wabanje si iteganyagihe.',
        'dimension_soil' => 'Ubutaka bubereye igihingwa',
        'dimension_climate' => 'Ikirere kibereye igihingwa',
        'dimension_stewardship' => 'Kubungabunga ubutaka',
        'soil_fit' => 'Ubutaka bubereye igihingwa',
        'climate_fit' => 'Ikirere kibereye igihingwa',
        'seasonal_weather_fit' => 'Ikirere cy’igihembwe kibereye igihingwa',
        'local_price_trend' => 'Ihindagurika ry’ibiciro byo ku isoko ry’aho',
        'balanced_nutrition_ha' => 'Intungamubiri ziringaniye/ha',
        'production_priority' => 'Icyiciro cy’umusaruro w’ibiribwa',
        'farm_plan_heading' => 'Igereranya ry’umusaruro n’intungamubiri',
        'farm_plan_intro' => 'Gereranya ibihingwa bitatu bya mbere ukoresheje umusaruro uteganyijwe, uko ikirere kibereye igihingwa n’ingufu na poroteyine bishobora kuboneka kuri hegitari. Iri ni igereranya ry’igenamigambi, si iteganyamibare ry’ibiribwa bizaboneka.',
        'plan_expected_harvest' => 'Umusaruro uteganyijwe',
        'plan_weather_fit' => 'Ikirere kibereye igihingwa',
        'plan_energy' => 'Ingufu / ha',
        'plan_protein' => 'Poroteyine / ha',
        'plan_evidence' => 'Inkomoko y’umusaruro n’intungamubiri',
        'plan_evidence_limit' => 'Igereranya rifata ko umusaruro wose ufite imiterere y’ibiribwa bifitanye isano; igice kiribwa, igihombo, kubika, gutunganya no gutegura ibiribwa ntibibarirwamo.',
        'evidence_quality_heading' => 'Inkomoko n’imbibi z’amakuru',
        'evidence_quality_intro' => 'Urutonde rwo kugenzura inkomoko—si amanota y’icyizere cy’imibare.',
        'evidence_yield' => 'Umusaruro',
        'evidence_nutrition' => 'Imiterere y’ibiribwa',
        'evidence_market' => 'Amakuru y’ibiciro',
        'evidence_weather' => 'Ikirere',
        'evidence_soil' => 'Ubutaka no kubungabunga ubutaka',
        'yield_record_source' => 'Umusaruro wapimwe mu karere uturutse kuri {publisher}, {years}. Ni amakuru yabanje, si iteganyagihe.',
        'yield_assumption_source' => 'Nta musaruro wapimwe uhuye n’akarere wabonetse; urutonde rukoresha umusaruro w’ikigereranyo. Ntukawufate nk’umusaruro wapimwe muri aka karere.',
        'food_profile_proxy' => 'Ibiribwa by’ikigereranyo byahujwe n’iki gihingwa: {food}; genzura imiterere y’ibiribwa byo mu karere.',
        'food_label_match' => 'Izina ry’ibiribwa rihuye n’iri mu bubiko; ubwoko n’uko byateguwe byo mu karere ntibyagenzuwe.',
        'open_fdc_record' => 'Fungura amakuru ya USDA FoodData Central',
        'food_source_missing' => 'Nta nyandiko y’inkomoko y’imiterere y’ibiribwa iboneka.',
        'market_quality_note' => 'Ibipimo {dates} by’amatariki kuva {first} kugeza {latest}. Ni ihindagurika ry’ibiciro, si inyungu; ikiguzi cyo guhinga no kugera ku isoko ntibibarirwamo.',
        'market_quality_missing' => 'Hapimwe amatariki atageze kuri abiri; ihindagurika ry’ibiciro ntiriboneka kandi amanota ni atabogamye.',
        'weather_quality_note' => 'Iteganyagihe rya Open-Meteo ryabonetse ku wa {date} rikubiyemo {coverage}% by’igihe cyatoranyijwe. Amanota y’ikirere akoresha ingano z’ikigereranyo z’ibihingwa, si igipimo cy’ubukwiriye cy’akarere.',
        'soil_quality_missing' => 'Nta manota y’ubukwiriye bw’ubutaka cyangwa kubungabunga ubutaka y’akarere yizewe; ibi bipimo ntibibarwa.',
        'district_chart_title' => 'Igereranya ry’umusaruro mu turere',
        'district_chart_intro' => 'Gereranya umusaruro wanditswe na NISR mu turere tw’u Rwanda mu bihembwe A na B. Ahatazwi hagaragazwa nka “Nta makuru yatangajwe,” si zeru.',
        'district_chart_crop' => 'Igihingwa',
        'district_chart_scale' => 'Utubari dukoresha igipimo kimwe kuri iki gihingwa; umusaruro munini wanditswe ni {maximum} kg/ha.',
        'district_chart_a' => 'Igihembwe A',
        'district_chart_b' => 'Igihembwe B',
        'district_chart_missing' => 'Nta makuru yatangajwe',
        'district_chart_error' => 'Igereranya ry’umusaruro mu turere ntiriboneka by’akanya gato.',
        'district_chart_source' => 'Inkomoko: NISR Seasonal Agricultural Survey, Igihembwe A Imbonerahamwe ya 13 n’Igihembwe B Imbonerahamwe ya 11, 2026. Iyi shusho igaragaza gusa umusaruro wanditswe; amakuru y’akarere n’igihingwa atatangajwe ntiyagereranywa.',
        'production_priority_note' => 'Icyiciro cy’umusaruro kigira uruhare rwa 20%: ibinyampeke, ibirayi n’ibinyamisogwe bishyirwa imbere; imboga zigabanyirizwa amanota mu guteganya guhinga ku buso bunini. Ibi ntibigabanya intungamubiri cyangwa ubukwiriye bw’ubutaka n’ikirere.',
        'observed_yield_score' => 'Umusaruro wapimwe mu karere',
        'observed_yield_scoring_note' => 'NISR ni yo ibanza gukoreshwa iyo ifite umusaruro uhuye n’akarere, igihingwa n’igihembwe. Iyo utabonetse, hakoreshwa amakuru mashya abitswe mu bundi bubiko rusange iyo ahari. Umusaruro wapimwe ugira uruhare rwa 40% mbere yo gushyiraho ikipimo cy’ibiribwa bihingirwa umusaruro mwinshi cya 20% (bityo ukagira uruhare rwa 32% mu manota ya nyuma iyo uhari). Ni amakuru y’umusaruro wabonetse kera, si iteganyagihe cyangwa inama yo guhinga.',
        'public_yield_source' => 'Inkomoko rusange',
        'soil_stewardship' => 'Kubungabunga ubutaka',
        'rank' => 'Urutonde',
        'risk_low' => 'ntoya',
        'risk_medium' => 'iringaniye',
        'risk_high' => 'nyinshi',
        'erosion_risk' => 'ibyago byo gutwarwa n’isuri',
        'expected_harvest' => 'Umusaruro uteganyijwe',
        'harvest_time' => 'Iminsi kuva uhinze kugeza usaruye bwa mbere',
        'harvest_time_unknown' => 'Igihe cyo gusarura ntikibitswe',
        'harvest_time_note' => 'Igihe ni ikigereranyo rusange, si amakuru yapimwe na NISR; ubwoko, ubutumburuke, imbuto zikoreshwa n’imicungire y’igihingwa bigira ingaruka ku gihe. Ibi ntibihindura uko ibihingwa bikurikirana.',
        'days' => 'iminsi',
        'yield_evidence' => 'Ibimenyetso by’umusaruro',
        'yield_illustrative' => 'Inkomoko y’ikigereranyo cy’umusaruro ntiyanditswe.',
        'soil_data' => 'Amakuru y’ubutaka',
        'record_updated' => 'Itariki amakuru y’ubukwiriye yavuguriweho',
        'soil_indicators' => 'Ibipimo byo kurinda no kuvugurura ubutaka',
        'erosion_protection' => 'Kurinda isuri',
        'nutrient_balance' => 'Intungamubiri ziringaniye / kugabanya igabanuka ryazo',
        'soil_structure' => 'Imiterere y’ubutaka n’ubushobozi bwo kubika ibisigazwa by’ibimera',
        'stewardship_guidance' => 'Rinda ubutaka ukoresheje ibimera bitwikira ubutaka bibereye aho, imicungire iringaniye y’intungamubiri n’uburyo bubungabunga imiterere y’ubutaka.',
        'suggested_rotation' => 'Ibihingwa byasimburana',
        'rotation_guidance' => 'Simburanya amoko y’ibihingwa kandi ushyiremo ibinyamisogwe cyangwa igihingwa gitwikira ubutaka kibereye aho, igihe biboneye mu buhinzi.',
        'provisional_guidance' => 'Aya manota n’uruhererekane rw’ibihingwa ni inama z’agateganyo; ingaruka ku butaka ziterwa n’aho buherereye n’imicungire yabwo.',
        'api_error' => 'Ntibyashobotse kubona ibyifuzo by’ibihingwa ubu. Ongera ugerageze.',
        'no_suitability' => 'Nta bihingwa bifite amakuru y’intungamubiri n’umusaruro akoreshwa aboneka kuri aka karere no kuri iki gihembwe.',
        'nutrient_calories_kcal' => 'Ingufu',
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
    ],
];
$t = static fn (string $key): string => $translations[$language][$key] ?? $translations['en'][$key] ?? $key;

send_security_headers();

$pdo = get_db_connection();
$districts = get_districts_list($pdo);

// Group districts by province for the selector, same pattern as the
// nutrient tool's nutrient groups.
$districtsByProvince = [];
foreach ($districts as $d) {
    $districtsByProvince[$d['province']][] = $d;
}
?>
<!DOCTYPE html>
<html lang="<?= h($language === 'rw' ? 'rw' : 'en') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="crop-advisor-i18n" content="<?= h(json_encode(['language' => $language, 'messages' => $translations[$language]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) ?>">
    <title>Umurima Data — <?= h($t('crop_advisor')) ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=track1-7">
    <link rel="stylesheet" href="assets/css/crop_advisor.css?v=track1-3">
</head>
<body>

<header class="site-header">
    <div class="wrap">
        <nav class="module-nav" aria-label="<?= h($language === 'rw' ? 'Kugenda ku rubuga' : 'Main navigation') ?>">
            <a href="index.php?lang=<?= h($language) ?>"><?= h($t('nutrition_tool')) ?></a>
            <span class="module-nav-current"><?= h($t('crop_advisor')) ?></span>
            <a href="nisr_data.php">Data sources</a>
            <form class="language-control" action="crop_advisor.php" method="get">
                <label for="language-select"><?= h($t('language')) ?> / Ururimi</label>
                <select id="language-select" name="lang">
                    <option value="en" lang="en"<?= $language === 'en' ? ' selected' : '' ?>>English</option>
                    <option value="rw" lang="rw"<?= $language === 'rw' ? ' selected' : '' ?>>Kinyarwanda</option>
                </select>
                <button type="submit"><?= h($t($language === 'rw' ? 'change' : 'apply')) ?></button>
            </form>
        </nav>
        <h1><?= h($t('page_title')) ?></h1>
        <p><?= h($t('intro')) ?></p>
    </div>
</header>

<main class="wrap">
    <aside class="soil-data-note">
        <strong><?= h($t('soil_status')) ?></strong>
        <p><strong><?= h($t('prototype_warning')) ?></strong> <?= h($t('soil_warning')) ?></p>
        <p><?= h($t('sas_note')) ?>
            <a href="assets/SAS%202026%20Season%20A%20_Final%20Report.pdf" target="_blank" rel="noopener noreferrer"><?= h($t('sas_a_report_link')) ?></a>
            and <a href="assets/SAS%202026B_Main%20report.pdf" target="_blank" rel="noopener noreferrer"><?= h($t('sas_b_report_link')) ?></a>.
            <?= h($t('sas_desc')) ?>
        </p>
        <a href="https://soilgrids.org/" target="_blank" rel="noopener noreferrer"><?= h($t('explore_soilgrids')) ?> <span aria-hidden="true">↗</span></a>
    </aside>

    <div class="controls-block">
        <label for="district-select"><?= h($t('district')) ?></label>
        <select id="district-select">
            <option value=""><?= h($t('choose_district')) ?></option>
            <?php foreach ($districtsByProvince as $province => $group): ?>
                <optgroup label="<?= h($province) ?>">
                    <?php foreach ($group as $d): ?>
                        <option value="<?= (int) $d['id'] ?>"><?= h($d['name']) ?></option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
    </div>

    <div id="crop-results">
        <p class="empty-state"><?= h($t('district_prompt')) ?></p>
    </div>
</main>

<footer class="site-footer">
    <?= h($t('footer')) ?>
</footer>

<script src="assets/js/crop_advisor.js?v=track1-10"></script>
</body>
</html>
