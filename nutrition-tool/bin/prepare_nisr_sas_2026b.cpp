/**
 * Prepare normalized observations from NISR SAS 2026 Season B report tables 6-33.
 *
 * Build with:
 *   g++ -std=c++17 -O2 -Wall -o bin/prepare_nisr_sas_2026b bin/prepare_nisr_sas_2026b.cpp
 */

#include <algorithm>
#include <array>
#include <cctype>
#include <chrono>
#include <cmath>
#include <cstdio>
#include <cstdlib>
#include <filesystem>
#include <fstream>
#include <iostream>
#include <iterator>
#include <map>
#include <regex>
#include <set>
#include <sstream>
#include <stdexcept>
#include <string>
#include <utility>
#include <vector>

#ifdef _WIN32
#include <process.h>
#endif

namespace fs = std::filesystem;

struct Column {
    std::string indicator;
    std::string subgroup;
};

struct Table {
    std::string title;
    std::string geography;
    std::string unit;
    std::vector<Column> columns;
};

struct Token {
    std::string text;
    std::size_t end;
};

struct Observation {
    std::array<std::string, 11> fields;
};

using TableMap = std::map<int, Table>;

void appendColumns(Table& table, const std::string& indicator, const std::vector<std::string>& groups)
{
    for (const auto& group : groups) {
        table.columns.push_back({indicator, group});
    }
}

void appendRepeatedColumns(
    Table& table,
    const std::vector<std::string>& indicators,
    const std::vector<std::string>& groups)
{
    for (const auto& indicator : indicators) {
        appendColumns(table, indicator, groups);
    }
}

TableMap buildTables()
{
    TableMap tables;
    auto add = [&](int number, std::string title, std::string geography, std::string unit) -> Table& {
        return tables.emplace(number, Table{std::move(title), std::move(geography), std::move(unit), {}})
            .first->second;
    };
    const std::vector<std::string> farmers = {"Overall", "SSF", "LSF"};
    const std::vector<std::string> seedSources = {
        "Government (MINAGRI/RAB/NAEB)", "Recognized seed multipliers", "Agro-dealers",
        "NGOs/companies", "Market", "Agriculture cooperative", "Other source", "Total",
    };
    const std::vector<std::string> fertilizerSources = {
        "Government (MINAGRI/RAB/NAEB)", "Agro-dealers", "NGOs/companies", "Market",
        "Agriculture cooperative", "Other source", "Total",
    };
    const std::vector<std::string> yieldCrops = {
        "Maize", "Sorghum", "Paddy rice", "Wheat", "Other cereals", "Cassava",
        "Sweet potatoes", "Irish potatoes", "Yams & Taro", "Bananas", "Cooking banana",
        "Dessert banana", "Banana for beer", "Beans", "Bush bean", "Climbing bean",
        "Peas", "Ground nuts", "Soya beans", "Vegetables", "Fruits", "Fodder crops",
        "Other crops",
    };

    auto& t6 = add(6, "Cultivated area, harvested area, production, and yield by crop", "crop", "mixed");
    for (const auto& column : std::vector<Column>{
             {"Cultivated area", "2026B"}, {"Cultivated area", "2025B"},
             {"Harvested area", "2026B"}, {"Harvested area", "2025B"},
             {"Production", "2026B"}, {"Production", "2025B"},
             {"Yield", "2026B"}, {"Yield", "2025B"}}) {
        t6.columns.push_back(column);
    }

    auto& t7 = add(7, "Agricultural land use per district", "district", "mixed");
    appendColumns(t7, "Total land area", {""});
    appendColumns(t7, "Agricultural land", {""});
    appendColumns(t7, "Share of agricultural land", {""});
    appendColumns(t7, "Arable land", {""});
    appendColumns(t7, "Physical cultivated land", {""});
    appendColumns(t7, "Area under seasonal crops", {""});
    appendColumns(t7, "Area under permanent crops", {""});
    appendColumns(t7, "Area under permanent pasture", {""});
    appendColumns(t7, "Temporary fallow land", {""});
    appendColumns(t7, "Temporarily meadow and pasture", {""});

    auto& t8 = add(8, "Area under agricultural practices", "district", "Hectares");
    for (const auto& indicator : {"Modern irrigated agricultural land", "Agricultural area under erosion control",
                                  "Agricultural area under agroforestry trees", "Agricultural area under inorganic fertilizer",
                                  "Agricultural area under organic fertilizer"}) {
        appendColumns(t8, indicator, {""});
    }

    const std::vector<std::string> areaCrops = {
        "Maize", "Sorghum", "Paddy rice", "Wheat", "Other cereals", "Cassava",
        "Sweet potato", "Irish potato", "Yams & Taro", "Banana", "Cooking banana",
        "Dessert banana", "Banana for beer", "Beans", "Bush bean", "Climbing bean",
        "Pea", "Groundnut", "Soybean", "Vegetables", "Fruits", "Fodder crops", "Other crops",
    };
    auto& t9 = add(9, "Cultivated area by crop type and district", "district", "Hectares");
    for (const auto& name : areaCrops) appendColumns(t9, "Cultivated area", {name});
    appendColumns(t9, "Total developed land", {""});

    const std::vector<std::string> harvestedCrops = {
        "Maize", "Sorghum", "Paddy rice", "Wheat", "Other cereals", "Cassava",
        "Sweet potato", "Irish potato", "Yams & Taro", "Bananas", "Cooking banana",
        "Dessert banana", "Banana for beer", "Beans", "Bush bean", "Climbing bean",
        "Pea", "Groundnut", "Soybean", "Vegetables", "Fruits", "Fodder crops", "Other crops",
    };
    auto& t10 = add(10, "Harvested area by crop type and district", "district", "Hectares");
    for (const auto& name : harvestedCrops) appendColumns(t10, "Harvested area", {name});
    appendColumns(t10, "All crops", {""});

    auto& t11 = add(11, "Average yield by crop type and district", "district", "Kilograms per hectare");
    auto& t12 = add(12, "Average yield of Large-Scale Farmers by crop type and district", "district", "Kilograms per hectare");
    for (const auto& name : yieldCrops) {
        appendColumns(t11, "Average yield", {name});
        appendColumns(t12, "Large-scale farmer average yield", {name});
    }
    auto& t13 = add(13, "Crop production by crop type and district", "district", "Metric tonnes");
    const std::vector<std::string> productionCrops = {
        "Maize", "Sorghum", "Paddy rice", "Wheat", "Other cereals", "Cassava",
        "Sweet potatoes", "Irish potatoes", "Yams & Taro", "Banana", "Cooking banana",
        "Dessert banana", "Banana for beer", "Beans", "Bush bean", "Climbing bean", "Pea",
        "Ground nuts", "Soya bean", "Vegetables", "Fruits", "Fodder crops", "Other crops",
    };
    for (const auto& name : productionCrops) appendColumns(t13, "Crop production", {name});

    auto& t14 = add(14, "Use of production by farmers", "crop", "Percentage");
    appendColumns(t14, "Use of production", {
        "Sold", "Own consumption", "Wages for hired labour", "Farm rent", "Offered as gift",
        "Barter trade/exchanged", "Seeds", "Fodder purpose", "Stored", "Post-harvesting losses", "Other usage",
    });
    auto& t15 = add(15, "Cultivated area by cropping system and district", "district", "Percentage");
    appendColumns(t15, "Cropping system share", {"Pure cropping", "Mixed cropping"});

    const std::vector<std::string> sowingDates = {
        "Before January", "01-15 January", "16-31 January", "01-15 February", "16-28 February",
        "01-15 March", "16-31 March", "After 31 March", "Other season", "Total",
    };
    auto& t16 = add(16, "Sowing dates by district", "district", "Percentage");
    auto& t17 = add(17, "Sowing dates by crop", "crop", "Percentage");
    appendColumns(t16, "Sowing date distribution", sowingDates);
    appendColumns(t17, "Sowing date distribution", sowingDates);

    auto& t18 = add(18, "Use of improved seeds by farmer type per district", "district", "Percentage");
    appendRepeatedColumns(t18, {"Farmers using improved seeds", "Sampled plots using improved seeds",
                                "Land size using improved seeds"}, farmers);
    auto& t19 = add(19, "Seed type by crop", "crop", "Percentage");
    appendColumns(t19, "Seed type share", {"Traditional seeds", "Improved seeds", "Total"});
    auto& t20 = add(20, "Farmers by source of improved seeds per district", "district", "Percentage");
    appendColumns(t20, "Improved seed source", seedSources);
    auto& t21 = add(21, "Crops by source of seeds", "crop", "Percentage");
    appendColumns(t21, "Seed source", {"Government (MINAGRI/RAB)", "Recognized seed multipliers",
                                       "Agro-dealers", "NGOs", "Market", "Agriculture cooperative",
                                       "Other source", "Total"});
    auto& t22 = add(22, "Use of organic fertilizer by farmer type per district", "district", "Percentage");
    appendRepeatedColumns(t22, {"Farmers applying organic fertilizer", "Plots applying organic fertilizer",
                                "Land size applying organic fertilizer"}, farmers);
    auto& t23 = add(23, "Use of inorganic fertilizer by farmer type per district", "district", "Percentage");
    appendRepeatedColumns(t23, {"Farmers using inorganic fertilizer", "Plots applying inorganic fertilizer",
                                "Land size applying inorganic fertilizer"}, farmers);
    auto& t24 = add(24, "Farmers by source of inorganic fertilizer per district", "district", "Percentage");
    appendColumns(t24, "Inorganic fertilizer source", fertilizerSources);
    auto& t25 = add(25, "Source of inorganic fertilizer by fertilizer type", "fertilizer", "Percentage");
    appendColumns(t25, "Fertilizer source", fertilizerSources);
    auto& t26 = add(26, "Plots by type of inorganic fertilizer per district", "district", "Percentage");
    appendColumns(t26, "Inorganic fertilizer plot share", {"NPK", "Urea", "DAP", "DI Grow", "Lime", "Others", "Total"});
    auto& t27 = add(27, "Use of pesticides by farmer type per district", "district", "Percentage");
    appendRepeatedColumns(t27, {"Farmers using pesticides", "Plots using pesticides",
                                "Land size using pesticides"}, farmers);
    auto& t28 = add(28, "Plots by type of pesticide per district", "district", "Percentage");
    appendColumns(t28, "Pesticide plot share", {
        "Dithane", "Ridomil", "Dimethoate", "Cypermethrin", "Rocket", "Beam", "Other pesticide", "Total",
    });
    auto& t29 = add(29, "Farmers practicing agricultural practices", "district", "Percentage");
    appendRepeatedColumns(t29, {"Farmers protecting land against erosion", "Farmers using mechanical equipment",
                                "Farmers practicing irrigation", "Farmers practicing agroforestry"}, farmers);
    auto& t30 = add(30, "Plots by types of irrigation used", "district", "Percentage");
    appendColumns(t30, "Irrigation type plot share", {
        "Surface irrigation", "Flood irrigation", "Drip irrigation", "Sprinkler irrigation",
        "Pivot irrigation", "Traditional techniques",
    });
    auto& t31 = add(31, "Plots by source of water used and district", "district", "Percentage");
    appendColumns(t31, "Water source plot share", {
        "Rainwater", "Water treatment", "Underground", "Lake/streams", "Water catchment", "Others",
    });
    auto& t32 = add(32, "Plots by categories of erosion control measures", "district", "Percentage");
    appendColumns(t32, "Erosion control measure plot share", {
        "Plots under erosion control measures", "Measures controlling low-rate erosion",
        "Measures controlling moderate-rate erosion", "Measures controlling severe-rate erosion",
    });
    auto& t33 = add(33, "Plots by degree of erosion per district", "district", "Percentage");
    appendColumns(t33, "Degree of erosion plot share", {"Severe erosion", "Moderate erosion", "Low erosion", "Very low erosion"});

    return tables;
}

const std::set<std::string> DISTRICTS = {
    "Nyarugenge", "Gasabo", "Kicukiro", "Nyanza", "Gisagara", "Nyaruguru", "Huye",
    "Nyamagabe", "Ruhango", "Muhanga", "Kamonyi", "Karongi", "Rutsiro", "Rubavu",
    "Nyabihu", "Ngororero", "Rusizi", "Nyamasheke", "Rulindo", "Gakenke", "Musanze",
    "Burera", "Gicumbi", "Rwamagana", "Nyagatare", "Gatsibo", "Kayonza", "Kirehe",
    "Ngoma", "Bugesera", "National",
};

const std::set<std::string> CROPS = {
    "Cereals", "Tubers and Roots", "Taro &Yams", "Taro & Yams", "Legumes and Pulses",
    "Beans", "Vegetables & Fruits", "Total", "Maize", "Sorghum", "Paddy rice", "Wheat",
    "Other cereals", "Sweet potato", "Irish potato", "Taro & Yams", "Taro & yams", "Cassava",
    "Bush bean", "Climbing bean", "Pea", "Groundnut", "Soybean", "Cooking banana",
    "Dessert banana", "Banana for beer", "Vegetables", "Fruits", "Fodder crops", "Other crops",
};

const std::set<std::string> FERTILIZERS = {
    "NPK 17-17-17", "NPK 20-10-10", "NPK 25-5-5", "NPK 22-6-12", "Other NPK", "Urea",
    "liquid urea (Mbonea M", "DAP", "KCL/MOP", "Omax", "Winner", "Yara Viva", "Amidas",
    "Rhizobium", "DI Grow", "Lime/Ishwagara", "Other type of fertilizer",
};

const std::array<std::string, 11> CSV_FIELDS = {
    "table_number", "table_title", "source_pdf_page", "geography_level", "geography_name",
    "indicator", "subgroup", "unit", "value", "raw_value", "value_status",
};

std::string trim(const std::string& value)
{
    const auto first = value.find_first_not_of(" \t\r\n");
    if (first == std::string::npos) return "";
    const auto last = value.find_last_not_of(" \t\r\n");
    return value.substr(first, last - first + 1);
}

std::string trimEntity(std::string name)
{
    name = trim(name);
    while (!name.empty() && (name.back() == ';' || name.back() == ',')) name.pop_back();
    return trim(name);
}

std::vector<std::string> splitPages(const std::string& text)
{
    std::vector<std::string> pages;
    std::size_t start = 0;
    while (true) {
        const std::size_t separator = text.find('\f', start);
        pages.push_back(text.substr(start, separator == std::string::npos ? separator : separator - start));
        if (separator == std::string::npos) break;
        start = separator + 1;
    }
    while (!pages.empty() && trim(pages.back()).empty()) pages.pop_back();
    return pages;
}

#ifndef _WIN32
std::string shellQuote(const std::string& value)
{
    std::string result = "'";
    for (char character : value) {
        if (character == '\'') result += "'\\''";
        else result += character;
    }
    return result + "'";
}
#endif

std::string readFile(const fs::path& path)
{
    std::ifstream file(path, std::ios::binary);
    if (!file) throw std::runtime_error("Could not read the extracted report text.");
    std::ostringstream contents;
    contents << file.rdbuf();
    return contents.str();
}

std::vector<std::string> readPdfPages(const fs::path& pdfPath)
{
    if (!fs::is_regular_file(pdfPath) || fs::file_size(pdfPath) < 5) {
        throw std::runtime_error("PDF does not exist or is empty: " + pdfPath.string());
    }
    if (fs::file_size(pdfPath) > 30u * 1024u * 1024u) {
        throw std::runtime_error("PDF exceeds the 30 MB extraction limit.");
    }
    std::ifstream pdf(pdfPath, std::ios::binary);
    std::array<char, 5> signature{};
    pdf.read(signature.data(), static_cast<std::streamsize>(signature.size()));
    if (std::string(signature.data(), signature.size()) != "%PDF-") {
        throw std::runtime_error("Input does not have a valid PDF signature.");
    }

    const char* configuredBinary = std::getenv("PDFTOTEXT_BIN");
    const std::string binary = configuredBinary != nullptr && *configuredBinary != '\0'
        ? configuredBinary : "pdftotext";
    const auto timestamp = std::chrono::steady_clock::now().time_since_epoch().count();
    const fs::path textPath = fs::temp_directory_path()
        / ("nisr-sas-" + std::to_string(timestamp) + ".txt");
#ifdef _WIN32
    const int exitCode = static_cast<int>(_spawnlp(
        _P_WAIT,
        binary.c_str(),
        binary.c_str(),
        "-layout",
        "-enc",
        "UTF-8",
        "-f",
        "31",
        "-l",
        "61",
        fs::absolute(pdfPath).string().c_str(),
        textPath.string().c_str(),
        nullptr));
#else
    const std::string command = shellQuote(binary) + " -layout -enc UTF-8 -f 31 -l 61 "
        + shellQuote(fs::absolute(pdfPath).string()) + " " + shellQuote(textPath.string());
    const int exitCode = std::system(command.c_str());
#endif
    if (exitCode != 0) {
        std::error_code ignored;
        fs::remove(textPath, ignored);
        throw std::runtime_error(
            "pdftotext failed. Install Poppler and ensure pdftotext is on PATH or set PDFTOTEXT_BIN."
        );
    }

    try {
        const auto pages = splitPages(readFile(textPath));
        std::error_code ignored;
        fs::remove(textPath, ignored);
        return pages;
    } catch (...) {
        std::error_code ignored;
        fs::remove(textPath, ignored);
        throw;
    }
}

std::vector<Token> findTokens(const std::string& line)
{
    static const std::regex tokenPattern(R"(-|\(NA\)|NA|[0-9][0-9,]*(?:\.[0-9]+)?)");
    std::vector<Token> tokens;
    for (std::sregex_iterator it(line.begin(), line.end(), tokenPattern), end; it != end; ++it) {
        const auto match = *it;
        const std::size_t start = static_cast<std::size_t>(match.position());
        const std::size_t finish = start + static_cast<std::size_t>(match.length());
        if ((start == 0 || std::isspace(static_cast<unsigned char>(line[start - 1])))
            && (finish == line.size() || std::isspace(static_cast<unsigned char>(line[finish])))) {
            tokens.push_back({match.str(), finish});
        }
    }
    return tokens;
}

bool contains(const std::set<std::string>& values, const std::string& value)
{
    return values.find(value) != values.end();
}

std::vector<std::pair<std::string, std::vector<Token>>> parseRows(
    const std::string& pageText,
    const std::string& geography)
{
    const std::set<std::string>* candidates = geography == "district" ? &DISTRICTS
        : geography == "crop" ? &CROPS : &FERTILIZERS;
    std::vector<std::pair<std::string, std::vector<Token>>> rows;
    std::istringstream lines(pageText);
    std::string line;
    while (std::getline(lines, line)) {
        auto tokens = findTokens(line);
        if (tokens.empty()) continue;
        const std::string name = trimEntity(line.substr(0, tokens.front().end - tokens.front().text.size()));
        if (contains(*candidates, name) || name == "SSF" || name == "LSF") {
            rows.emplace_back(name, std::move(tokens));
        }
    }
    return rows;
}

std::vector<double> clusterColumnEnds(const std::vector<std::size_t>& ends, std::size_t expectedCount)
{
    std::map<std::size_t, std::vector<std::size_t>> frequencies;
    for (const auto position : ends) frequencies[position].push_back(position);
    std::vector<std::vector<std::size_t>> clusters;
    for (const auto& [position, occurrences] : frequencies) {
        for (const auto ignored : occurrences) {
            (void) ignored;
            if (clusters.empty() || position - clusters.back().back() > 2) {
                clusters.push_back({position});
            } else {
                clusters.back().push_back(position);
            }
        }
    }

    while (clusters.size() > expectedCount) {
        std::size_t closest = 0;
        std::size_t distance = clusters[1].front() - clusters[0].back();
        for (std::size_t i = 1; i + 1 < clusters.size(); ++i) {
            const auto candidateDistance = clusters[i + 1].front() - clusters[i].back();
            if (candidateDistance < distance) {
                closest = i;
                distance = candidateDistance;
            }
        }
        clusters[closest].insert(
            clusters[closest].end(), clusters[closest + 1].begin(), clusters[closest + 1].end());
        clusters.erase(clusters.begin() + static_cast<std::ptrdiff_t>(closest + 1));
    }
    if (clusters.size() != expectedCount) {
        throw std::runtime_error(
            "Expected " + std::to_string(expectedCount) + " numeric columns, detected "
            + std::to_string(clusters.size()) + ".");
    }

    std::vector<double> anchors;
    for (const auto& cluster : clusters) {
        double total = 0.0;
        for (const auto position : cluster) total += static_cast<double>(position);
        anchors.push_back(total / static_cast<double>(cluster.size()));
    }
    return anchors;
}

std::string observationUnit(int tableNumber, const std::string& indicator, const std::string& defaultUnit)
{
    if (tableNumber == 6) {
        if (indicator == "Cultivated area" || indicator == "Harvested area") return "Hectares";
        if (indicator == "Production") return "Metric tonnes";
        if (indicator == "Yield") return "Metric tonnes per hectare";
    } else if (tableNumber == 7) {
        return indicator == "Share of agricultural land" ? "Percentage" : "Thousand hectares";
    }
    return defaultUnit;
}

std::vector<Observation> extractPage(const std::string& pageText, int pdfPage, int tableNumber, const Table& table)
{
    const auto rows = parseRows(pageText, table.geography);
    if (rows.empty()) {
        throw std::runtime_error("Table " + std::to_string(tableNumber)
            + " has no recognized data rows on PDF page " + std::to_string(pdfPage) + ".");
    }

    std::vector<std::size_t> positions;
    for (const auto& [name, tokens] : rows) {
        (void) name;
        if (tokens.size() == table.columns.size()) {
            for (const auto& token : tokens) positions.push_back(token.end);
        }
    }
    if (positions.empty()) {
        throw std::runtime_error("Table " + std::to_string(tableNumber)
            + " has no complete row for column alignment.");
    }
    const auto anchors = clusterColumnEnds(positions, table.columns.size());

    std::vector<Observation> observations;
    for (const auto& [name, tokens] : rows) {
        std::vector<std::string> values(table.columns.size());
        for (const auto& token : tokens) {
            const auto nearest = std::min_element(anchors.begin(), anchors.end(), [&](double left, double right) {
                return std::abs(left - static_cast<double>(token.end))
                    < std::abs(right - static_cast<double>(token.end));
            });
            const std::size_t index = static_cast<std::size_t>(nearest - anchors.begin());
            if (std::abs(*nearest - static_cast<double>(token.end)) > 4.0 || !values[index].empty()) {
                throw std::runtime_error(
                    "Table " + std::to_string(tableNumber) + ", PDF page " + std::to_string(pdfPage)
                    + ", row " + name + ": ambiguous numeric column alignment.");
            }
            values[index] = token.text;
        }

        for (std::size_t i = 0; i < table.columns.size(); ++i) {
            const auto& column = table.columns[i];
            const std::string& rawValue = values[i];
            std::string value;
            std::string status;
            if (rawValue.empty()) {
                status = "blank";
            } else if (rawValue == "-") {
                status = "dash";
            } else if (rawValue == "NA" || rawValue == "(NA)") {
                status = "not_available";
            } else {
                status = "reported";
                std::copy_if(rawValue.begin(), rawValue.end(), std::back_inserter(value),
                    [](char character) { return character != ','; });
            }

            std::string geographyLevel = table.geography;
            if (name == "National") geographyLevel = "national";
            else if (name == "SSF" || name == "LSF") geographyLevel = "farmer_type";
            observations.push_back({{
                std::to_string(tableNumber),
                table.title,
                std::to_string(pdfPage),
                geographyLevel,
                name,
                column.indicator,
                column.subgroup,
                observationUnit(tableNumber, column.indicator, table.unit),
                value,
                rawValue,
                status,
            }});
        }
    }
    return observations;
}

std::string csvEscape(const std::string& value)
{
    if (value.find_first_of(",\"\r\n") == std::string::npos) return value;
    std::string escaped = "\"";
    for (const char character : value) {
        if (character == '"') escaped += "\"\"";
        else escaped += character;
    }
    return escaped + '"';
}

void writeCsv(const fs::path& outputPath, const std::vector<Observation>& rows)
{
    if (outputPath.has_parent_path()) fs::create_directories(outputPath.parent_path());
    std::ofstream output(outputPath, std::ios::binary | std::ios::trunc);
    if (!output) throw std::runtime_error("Could not create output CSV: " + outputPath.string());
    output << "\xEF\xBB\xBF";
    for (std::size_t i = 0; i < CSV_FIELDS.size(); ++i) {
        if (i > 0) output << ',';
        output << CSV_FIELDS[i];
    }
    output << "\r\n";
    for (const auto& row : rows) {
        for (std::size_t i = 0; i < row.fields.size(); ++i) {
            if (i > 0) output << ',';
            output << csvEscape(row.fields[i]);
        }
        output << "\r\n";
    }
    if (!output) throw std::runtime_error("Failed while writing output CSV: " + outputPath.string());
}

int main(int argc, char** argv)
{
    if (argc != 3) {
        std::cerr << "Usage: prepare_nisr_sas_2026b <report.pdf> <output.csv>\n";
        return 2;
    }

    try {
        const auto pages = readPdfPages(argv[1]);
        if (pages.size() != 31) {
            throw std::runtime_error("Expected 31 report pages (31-61), got "
                + std::to_string(pages.size()) + ".");
        }
        const TableMap tables = buildTables();
        const std::regex headingPattern(R"(Table\s+(\d+)\s*:)");
        std::vector<Observation> observations;
        for (std::size_t index = 0; index < pages.size(); ++index) {
            const int pdfPage = 31 + static_cast<int>(index);
            std::smatch heading;
            if (!std::regex_search(pages[index], heading, headingPattern)) continue;
            const int tableNumber = std::stoi(heading[1].str());
            const auto table = tables.find(tableNumber);
            if (table == tables.end()) {
                throw std::runtime_error("Unexpected table " + std::to_string(tableNumber)
                    + " on PDF page " + std::to_string(pdfPage) + ".");
            }
            const int expectedPage = tableNumber == 6 ? 31 : tableNumber + 28;
            if (pdfPage != expectedPage) {
                throw std::runtime_error("Table " + std::to_string(tableNumber)
                    + " is on unexpected PDF page " + std::to_string(pdfPage)
                    + "; expected " + std::to_string(expectedPage) + ".");
            }
            auto pageObservations = extractPage(pages[index], pdfPage, tableNumber, table->second);
            observations.insert(observations.end(), pageObservations.begin(), pageObservations.end());
        }
        if (observations.empty()) throw std::runtime_error("No report tables 6-33 were extracted.");
        writeCsv(argv[2], observations);
        std::cout << "Prepared " << observations.size()
            << " normalized observations from tables 6-33.\n";
    } catch (const std::exception& error) {
        std::cerr << error.what() << '\n';
        return 1;
    }
    return 0;
}
