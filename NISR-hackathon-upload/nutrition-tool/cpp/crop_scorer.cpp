/**
 * crop_scorer.cpp — Phase 2 Crop Advisor scoring engine.
 *
 * Reads a small JSON document from stdin describing candidate crops
 * for one district (each with a soil/climate/market/nutrition
 * subscore, 0-100) plus a set of weights, computes a weighted
 * composite score for each candidate, and writes the ranked result
 * as JSON to stdout.
 *
 * Why a hand-rolled JSON parser instead of a library: the interchange
 * format between this program and its one caller (includes/crop_scoring.php)
 * is small and fully under our control, so a compact, dependency-free
 * recursive-descent parser keeps the build to a single `g++` command
 * with no external headers to vendor or fetch — see README "Building
 * the scoring engine". It still parses real JSON (objects, arrays,
 * strings, numbers, booleans, null), it's just scoped to what this
 * project needs rather than being a general-purpose library.
 *
 * Build:  g++ -std=c++17 -O2 -Wall -o bin/crop_scorer cpp/crop_scorer.cpp
 * Run:    echo '{"weights":{...},"candidates":[...]}' | ./bin/crop_scorer
 */

#include <iostream>
#include <sstream>
#include <string>
#include <vector>
#include <utility>
#include <stdexcept>
#include <cmath>
#include <algorithm>
#include <memory>

// ------------------------------------------------------------------
// Minimal JSON value + parser + serializer
// ------------------------------------------------------------------

enum class JsonType { Null, Bool, Number, String, Array, Object };

struct JsonValue {
    JsonType type = JsonType::Null;
    bool boolValue = false;
    double numberValue = 0.0;
    std::string stringValue;
    std::vector<JsonValue> arrayValue;
    // Object preserves insertion order (a plain map would resort keys,
    // which isn't wrong here but makes debug output harder to read).
    std::vector<std::pair<std::string, JsonValue>> objectValue;

    static JsonValue makeObject() { JsonValue v; v.type = JsonType::Object; return v; }
    static JsonValue makeArray() { JsonValue v; v.type = JsonType::Array; return v; }
    static JsonValue makeNumber(double n) { JsonValue v; v.type = JsonType::Number; v.numberValue = n; return v; }
    static JsonValue makeString(const std::string& s) { JsonValue v; v.type = JsonType::String; v.stringValue = s; return v; }

    bool isObject() const { return type == JsonType::Object; }
    bool isArray() const { return type == JsonType::Array; }

    /** Look up a key in an object; returns nullptr if absent or not an object. */
    const JsonValue* find(const std::string& key) const {
        if (type != JsonType::Object) return nullptr;
        for (const auto& kv : objectValue) {
            if (kv.first == key) return &kv.second;
        }
        return nullptr;
    }

    void set(const std::string& key, JsonValue value) {
        objectValue.emplace_back(key, std::move(value));
    }

    double requireNumber(const std::string& key) const {
        const JsonValue* v = find(key);
        if (v == nullptr || v->type != JsonType::Number) {
            throw std::runtime_error("Missing or non-numeric field: " + key);
        }
        return v->numberValue;
    }

    std::string requireString(const std::string& key) const {
        const JsonValue* v = find(key);
        if (v == nullptr || v->type != JsonType::String) {
            throw std::runtime_error("Missing or non-string field: " + key);
        }
        return v->stringValue;
    }

    // Optional number field with a default, used for the "weights"
    // object so a caller can omit a weight and get a sane fallback.
    double optionalNumber(const std::string& key, double fallback) const {
        const JsonValue* v = find(key);
        if (v == nullptr || v->type != JsonType::Number) return fallback;
        return v->numberValue;
    }
};

class JsonParser {
public:
    explicit JsonParser(const std::string& text) : text_(text), pos_(0) {}

    JsonValue parse() {
        skipWhitespace();
        JsonValue v = parseValue();
        skipWhitespace();
        if (pos_ != text_.size()) {
            throw std::runtime_error("Unexpected trailing content in JSON input");
        }
        return v;
    }

private:
    const std::string& text_;
    size_t pos_;

    char peek() {
        if (pos_ >= text_.size()) throw std::runtime_error("Unexpected end of JSON input");
        return text_[pos_];
    }

    char next() {
        char c = peek();
        pos_++;
        return c;
    }

    void skipWhitespace() {
        while (pos_ < text_.size() && std::isspace(static_cast<unsigned char>(text_[pos_]))) {
            pos_++;
        }
    }

    void expect(char c) {
        if (peek() != c) {
            throw std::runtime_error(std::string("Expected '") + c + "' in JSON input");
        }
        pos_++;
    }

    bool matchLiteral(const std::string& literal) {
        if (text_.compare(pos_, literal.size(), literal) == 0) {
            pos_ += literal.size();
            return true;
        }
        return false;
    }

    JsonValue parseValue() {
        skipWhitespace();
        char c = peek();
        if (c == '{') return parseObject();
        if (c == '[') return parseArray();
        if (c == '"') return parseString();
        if (c == 't' || c == 'f') return parseBool();
        if (c == 'n') return parseNull();
        if (c == '-' || std::isdigit(static_cast<unsigned char>(c))) return parseNumber();
        throw std::runtime_error("Unexpected character in JSON input");
    }

    JsonValue parseObject() {
        expect('{');
        JsonValue obj = JsonValue::makeObject();
        skipWhitespace();
        if (peek() == '}') { pos_++; return obj; }
        while (true) {
            skipWhitespace();
            JsonValue key = parseString();
            skipWhitespace();
            expect(':');
            JsonValue value = parseValue();
            obj.objectValue.emplace_back(key.stringValue, std::move(value));
            skipWhitespace();
            char c = next();
            if (c == ',') continue;
            if (c == '}') break;
            throw std::runtime_error("Expected ',' or '}' in JSON object");
        }
        return obj;
    }

    JsonValue parseArray() {
        expect('[');
        JsonValue arr = JsonValue::makeArray();
        skipWhitespace();
        if (peek() == ']') { pos_++; return arr; }
        while (true) {
            JsonValue value = parseValue();
            arr.arrayValue.push_back(std::move(value));
            skipWhitespace();
            char c = next();
            if (c == ',') continue;
            if (c == ']') break;
            throw std::runtime_error("Expected ',' or ']' in JSON array");
        }
        return arr;
    }

    JsonValue parseString() {
        expect('"');
        std::string out;
        while (true) {
            char c = next();
            if (c == '"') break;
            if (c == '\\') {
                char esc = next();
                switch (esc) {
                    case '"': out += '"'; break;
                    case '\\': out += '\\'; break;
                    case '/': out += '/'; break;
                    case 'n': out += '\n'; break;
                    case 't': out += '\t'; break;
                    case 'r': out += '\r'; break;
                    case 'b': out += '\b'; break;
                    case 'f': out += '\f'; break;
                    case 'u': {
                        // Minimal \uXXXX support: only handles the
                        // basic multilingual plane, which is all our
                        // controlled input (district/crop names) needs.
                        if (pos_ + 4 > text_.size()) throw std::runtime_error("Bad \\u escape in JSON string");
                        std::string hex = text_.substr(pos_, 4);
                        pos_ += 4;
                        int codepoint = std::stoi(hex, nullptr, 16);
                        if (codepoint < 0x80) {
                            out += static_cast<char>(codepoint);
                        } else if (codepoint < 0x800) {
                            out += static_cast<char>(0xC0 | (codepoint >> 6));
                            out += static_cast<char>(0x80 | (codepoint & 0x3F));
                        } else {
                            out += static_cast<char>(0xE0 | (codepoint >> 12));
                            out += static_cast<char>(0x80 | ((codepoint >> 6) & 0x3F));
                            out += static_cast<char>(0x80 | (codepoint & 0x3F));
                        }
                        break;
                    }
                    default:
                        throw std::runtime_error("Unknown escape sequence in JSON string");
                }
            } else {
                out += c;
            }
        }
        return JsonValue::makeString(out);
    }

    JsonValue parseNumber() {
        size_t start = pos_;
        if (peek() == '-') pos_++;
        while (pos_ < text_.size() && std::isdigit(static_cast<unsigned char>(text_[pos_]))) pos_++;
        if (pos_ < text_.size() && text_[pos_] == '.') {
            pos_++;
            while (pos_ < text_.size() && std::isdigit(static_cast<unsigned char>(text_[pos_]))) pos_++;
        }
        if (pos_ < text_.size() && (text_[pos_] == 'e' || text_[pos_] == 'E')) {
            pos_++;
            if (pos_ < text_.size() && (text_[pos_] == '+' || text_[pos_] == '-')) pos_++;
            while (pos_ < text_.size() && std::isdigit(static_cast<unsigned char>(text_[pos_]))) pos_++;
        }
        std::string numStr = text_.substr(start, pos_ - start);
        if (numStr.empty() || numStr == "-") throw std::runtime_error("Invalid number in JSON input");
        return JsonValue::makeNumber(std::stod(numStr));
    }

    JsonValue parseBool() {
        if (matchLiteral("true")) { JsonValue v; v.type = JsonType::Bool; v.boolValue = true; return v; }
        if (matchLiteral("false")) { JsonValue v; v.type = JsonType::Bool; v.boolValue = false; return v; }
        throw std::runtime_error("Invalid literal in JSON input");
    }

    JsonValue parseNull() {
        if (matchLiteral("null")) { JsonValue v; v.type = JsonType::Null; return v; }
        throw std::runtime_error("Invalid literal in JSON input");
    }
};

std::string jsonEscape(const std::string& s) {
    std::string out;
    out.reserve(s.size() + 8);
    for (char c : s) {
        switch (c) {
            case '"': out += "\\\""; break;
            case '\\': out += "\\\\"; break;
            case '\n': out += "\\n"; break;
            case '\t': out += "\\t"; break;
            case '\r': out += "\\r"; break;
            default: out += c;
        }
    }
    return out;
}

std::string formatNumber(double n) {
    // Round to 3 decimal places for a stable, readable output — this
    // is a display score, not a value anyone needs float precision on.
    double rounded = std::round(n * 1000.0) / 1000.0;
    std::ostringstream oss;
    oss << rounded;
    return oss.str();
}

void serialize(const JsonValue& v, std::ostringstream& out) {
    switch (v.type) {
        case JsonType::Null:
            out << "null";
            break;
        case JsonType::Bool:
            out << (v.boolValue ? "true" : "false");
            break;
        case JsonType::Number:
            out << formatNumber(v.numberValue);
            break;
        case JsonType::String:
            out << '"' << jsonEscape(v.stringValue) << '"';
            break;
        case JsonType::Array: {
            out << '[';
            for (size_t i = 0; i < v.arrayValue.size(); i++) {
                if (i > 0) out << ',';
                serialize(v.arrayValue[i], out);
            }
            out << ']';
            break;
        }
        case JsonType::Object: {
            out << '{';
            for (size_t i = 0; i < v.objectValue.size(); i++) {
                if (i > 0) out << ',';
                out << '"' << jsonEscape(v.objectValue[i].first) << "\":";
                serialize(v.objectValue[i].second, out);
            }
            out << '}';
            break;
        }
    }
}

std::string toJsonString(const JsonValue& v) {
    std::ostringstream out;
    serialize(v, out);
    return out.str();
}

// ------------------------------------------------------------------
// Scoring domain logic
// ------------------------------------------------------------------

struct Weights {
    double soil = 0.25;
    double climate = 0.20;
    double market = 0.20;
    double nutrition = 0.15;
    double stewardship = 0.20;
};

Weights parseWeights(const JsonValue& root) {
    Weights w;
    const JsonValue* weightsNode = root.find("weights");
    if (weightsNode != nullptr && weightsNode->isObject()) {
        w.soil = weightsNode->optionalNumber("soil", w.soil);
        w.climate = weightsNode->optionalNumber("climate", w.climate);
        w.market = weightsNode->optionalNumber("market", w.market);
        w.nutrition = weightsNode->optionalNumber("nutrition", w.nutrition);
        w.stewardship = weightsNode->optionalNumber("stewardship", w.stewardship);
    }

    double sum = w.soil + w.climate + w.market + w.nutrition + w.stewardship;
    if (sum <= 0.0) {
        throw std::runtime_error("Weights must sum to a positive number");
    }
    // Normalise so the caller doesn't have to hand-tune weights to sum
    // to exactly 1.0 — passing {"soil": 2, "climate": 1, ...} works too.
    w.soil /= sum;
    w.climate /= sum;
    w.market /= sum;
    w.nutrition /= sum;
    w.stewardship /= sum;
    return w;
}

double clampScore(double v) {
    return std::max(0.0, std::min(100.0, v));
}

int main() {
    std::ostringstream buffer;
    buffer << std::cin.rdbuf();
    std::string input = buffer.str();

    try {
        if (input.empty()) {
            throw std::runtime_error("No input received on stdin");
        }

        JsonParser parser(input);
        JsonValue root = parser.parse();

        if (!root.isObject()) {
            throw std::runtime_error("Top-level JSON input must be an object");
        }

        Weights weights = parseWeights(root);

        const JsonValue* candidatesNode = root.find("candidates");
        if (candidatesNode == nullptr || !candidatesNode->isArray()) {
            throw std::runtime_error("Missing or invalid 'candidates' array");
        }

        struct Scored {
            std::string cropName;
            double cropId;
            double soil, climate, market, nutrition;
            double erosionControl, nutrientBalance, soilStructure, stewardship, composite;
        };

        std::vector<Scored> scored;
        scored.reserve(candidatesNode->arrayValue.size());

        for (const JsonValue& candidate : candidatesNode->arrayValue) {
            if (!candidate.isObject()) {
                throw std::runtime_error("Each candidate must be a JSON object");
            }

            Scored s;
            s.cropId = candidate.requireNumber("crop_id");
            s.cropName = candidate.requireString("crop_name");
            s.soil = clampScore(candidate.requireNumber("soil_score"));
            s.climate = clampScore(candidate.requireNumber("climate_score"));
            s.market = clampScore(candidate.requireNumber("market_score"));
            s.nutrition = clampScore(candidate.requireNumber("nutrition_score"));
            s.erosionControl = clampScore(candidate.requireNumber("erosion_control_score"));
            s.nutrientBalance = clampScore(candidate.requireNumber("nutrient_balance_score"));
            s.soilStructure = clampScore(candidate.requireNumber("soil_structure_score"));
            s.stewardship = (s.erosionControl * 0.40)
                           + (s.nutrientBalance * 0.35)
                           + (s.soilStructure * 0.25);

            s.composite = (s.soil * weights.soil)
                        + (s.climate * weights.climate)
                        + (s.market * weights.market)
                        + (s.nutrition * weights.nutrition)
                        + (s.stewardship * weights.stewardship);

            scored.push_back(s);
        }

        std::sort(scored.begin(), scored.end(), [](const Scored& a, const Scored& b) {
            return a.composite > b.composite;
        });

        JsonValue resultsArray = JsonValue::makeArray();
        for (const Scored& s : scored) {
            JsonValue row = JsonValue::makeObject();
            row.set("crop_id", JsonValue::makeNumber(s.cropId));
            row.set("crop_name", JsonValue::makeString(s.cropName));
            row.set("soil_score", JsonValue::makeNumber(s.soil));
            row.set("climate_score", JsonValue::makeNumber(s.climate));
            row.set("market_score", JsonValue::makeNumber(s.market));
            row.set("nutrition_score", JsonValue::makeNumber(s.nutrition));
            row.set("erosion_control_score", JsonValue::makeNumber(s.erosionControl));
            row.set("nutrient_balance_score", JsonValue::makeNumber(s.nutrientBalance));
            row.set("soil_structure_score", JsonValue::makeNumber(s.soilStructure));
            row.set("soil_stewardship_score", JsonValue::makeNumber(s.stewardship));
            row.set("composite_score", JsonValue::makeNumber(s.composite));
            resultsArray.arrayValue.push_back(std::move(row));
        }

        JsonValue output = JsonValue::makeObject();
        output.set("results", std::move(resultsArray));

        JsonValue weightsUsed = JsonValue::makeObject();
        weightsUsed.set("soil", JsonValue::makeNumber(weights.soil));
        weightsUsed.set("climate", JsonValue::makeNumber(weights.climate));
        weightsUsed.set("market", JsonValue::makeNumber(weights.market));
        weightsUsed.set("nutrition", JsonValue::makeNumber(weights.nutrition));
        weightsUsed.set("stewardship", JsonValue::makeNumber(weights.stewardship));
        output.set("weights_used", std::move(weightsUsed));

        std::cout << toJsonString(output) << std::endl;
        return 0;

    } catch (const std::exception& e) {
        JsonValue errOut = JsonValue::makeObject();
        errOut.set("error", JsonValue::makeString(e.what()));
        std::cout << toJsonString(errOut) << std::endl;
        return 1;
    }
}
