# Real Estate Valuation API Documentation

## Base URL
```
http://localhost/dannCleanly/wp-json/reval/v1
```

## Authentication
For admin endpoints, you need to include an authorization header:
```
Authorization: Bearer YOUR_ADMIN_TOKEN
```

## Endpoints

### 1. Upload Data
**POST** `/upload`

Upload CSV or XLSX files with property data.

**Headers:**
- `Authorization: Bearer {admin_token}` (required)

**Body:**
- `file`: CSV or XLSX file (multipart/form-data)

**Example Response:**
```json
{
  "status": "ok",
  "summary": {
    "communes": 102,
    "weights": {
      "habitable": 1,
      "balcony": 0.3,
      "terrace": 0.35,
      "private_garden": 0.15,
      "shared_garden": 0.08,
      "cellar": 0.6
    },
    "forfaits": {
      "garage_box": 27500,
      "parking_indoor": 16500,
      "parking_outdoor": 7500
    },
    "yearAdjust": {
      "before1990": -0.19,
      "after1990": -0.38
    }
  }
}
```

### 2. Property Evaluation
**POST** `/evaluate`

Evaluate a property and get its estimated value.

**Headers:**
- `Content-Type: application/json`

**Required Fields:**
- `type`: Property type (apartment, house, villa)
- `commune`: Commune name
- `living_m2`: Living area in square meters

**Optional Fields:**
- `land_ares`: Land area in ares (default: 0)
- `bedrooms`: Number of bedrooms (default: 0)
- `balcony_m2`: Balcony area in m² (default: 0)
- `terrace_m2`: Terrace area in m² (default: 0)
- `private_garden_m2`: Private garden area in m² (default: 0)
- `shared_garden_m2`: Shared garden area in m² (default: 0)
- `cellar_m2`: Cellar area in m² (default: 0)
- `garage_box_units`: Number of garage boxes (default: 0)
- `parking_indoor_units`: Number of indoor parking spaces (default: 0)
- `parking_outdoor_units`: Number of outdoor parking spaces (default: 0)
- `apply_year_coef`: Apply year coefficient (yes/no, default: no)
- `year_built`: Year the property was built
- `energy_efficiency`: Energy efficiency grade (A-G, default: C)
- `location_type`: Location type (city_center, residential, suburban, rural)
- `condition`: Property condition (excellent, good, average, poor, very_poor)
- `special_features`: Object with special features and their bonuses
- `custom_adjustments`: Object with custom adjustment percentages

**Example Request:**
```json
{
  "type": "apartment",
  "commune": "Luxembourg-Ville",
  "land_ares": 0,
  "living_m2": 85,
  "bedrooms": 2,
  "balcony_m2": 8,
  "terrace_m2": 0,
  "private_garden_m2": 0,
  "shared_garden_m2": 0,
  "cellar_m2": 5,
  "garage_box_units": 1,
  "parking_indoor_units": 0,
  "parking_outdoor_units": 0,
  "apply_year_coef": "yes",
  "year_built": 2015,
  "energy_efficiency": "B",
  "location_type": "city_center",
  "condition": "good",
  "special_features": {
    "swimming_pool": 0.15,
    "home_cinema": 0.05
  },
  "custom_adjustments": {
    "market_premium": 0.08
  }
}
```

**Example Response:**
```json
{
  "status": "ok",
  "result": {
    "prix_m2": 9800,
    "weighted_m2": 88.8,
    "valeur_batie": 870240,
    "land_value": 0,
    "bedroom_bonus": 98000,
    "forfaits_total": 27500,
    "coef_year": 0,
    "coef_type": -0.05,
    "energy_bonus": 0.05,
    "location_factor": 0.15,
    "condition_factor": 0.05,
    "special_features_bonus": 0.20,
    "custom_adjustments_total": 0.08,
    "total_coefficient": 1.38,
    "valeur_totale": 1305000,
    "range": {
      "low": 1239750,
      "mid": 1305000,
      "high": 1370250
    },
    "breakdown": {
      "base_value": 945740,
      "coefficients": {
        "year": 0,
        "type": -0.05,
        "energy": 0.05,
        "location": 0.15,
        "condition": 0.05,
        "special_features": 0.20,
        "custom": 0.08
      }
    },
    "evaluation_id": "eval_1234567890",
    "timestamp": "2024-01-15 10:30:00"
  }
}
```

### 3. Get Evaluations
**GET** `/evaluations`

Get a list of all evaluations with pagination.

**Headers:**
- `Authorization: Bearer {admin_token}` (required)

**Query Parameters:**
- `limit`: Number of evaluations to return (default: 50)
- `offset`: Number of evaluations to skip (default: 0)

**Example Request:**
```
GET /evaluations?limit=20&offset=0
```

**Example Response:**
```json
{
  "evaluations": {
    "eval_1234567890": {
      "id": "eval_1234567890",
      "input": {
        "type": "apartment",
        "commune": "Luxembourg-Ville",
        "living_m2": 85
      },
      "result": {
        "valeur_totale": 1305000
      },
      "user_id": 1,
      "timestamp": "2024-01-15 10:30:00",
      "ip_address": "192.168.1.100",
      "user_agent": "Mozilla/5.0..."
    }
  }
}
```

### 4. Get Specific Evaluation
**GET** `/evaluations/{id}`

Get detailed information about a specific evaluation.

**Headers:**
- `Authorization: Bearer {admin_token}` (required)

**Example Request:**
```
GET /evaluations/eval_1234567890
```

**Example Response:**
```json
{
  "evaluation": {
    "id": "eval_1234567890",
    "input": {
      "type": "apartment",
      "commune": "Luxembourg-Ville",
      "living_m2": 85,
      "bedrooms": 2,
      "energy_efficiency": "B",
      "location_type": "city_center",
      "condition": "good"
    },
    "result": {
      "prix_m2": 9800,
      "weighted_m2": 88.8,
      "valeur_batie": 870240,
      "valeur_totale": 1305000,
      "range": {
        "low": 1239750,
        "mid": 1305000,
        "high": 1370250
      }
    },
    "user_id": 1,
    "timestamp": "2024-01-15 10:30:00",
    "ip_address": "192.168.1.100",
    "user_agent": "Mozilla/5.0..."
  }
}
```

### 5. Get Statistics
**GET** `/statistics`

Get analytics and statistics about evaluations.

**Headers:**
- `Authorization: Bearer {admin_token}` (required)

**Example Response:**
```json
{
  "statistics": {
    "total_evaluations": 150,
    "average_value": 850000,
    "most_common_commune": "Luxembourg-Ville",
    "most_common_type": "apartment",
    "value_range": {
      "min": 250000,
      "max": 2500000
    }
  }
}
```

### 6. Get Formulas
**GET** `/formulas`

Get current formula configurations.

**Headers:**
- `Authorization: Bearer {admin_token}` (required)

**Example Response:**
```json
{
  "formulas": {
    "land_coefficient": 0.1,
    "bedroom_bonus_multiplier": 5,
    "type_coefficients": {
      "apartment": -0.05,
      "house": 0.0,
      "villa": 0.10
    },
    "range_low_percent": -0.05,
    "range_high_percent": 0.05,
    "energy_efficiency_bonus": {
      "A": 0.10,
      "B": 0.05,
      "C": 0.0,
      "D": -0.05,
      "E": -0.10,
      "F": -0.15,
      "G": -0.20
    },
    "location_factors": {
      "city_center": 0.15,
      "residential": 0.05,
      "suburban": 0.0,
      "rural": -0.10
    },
    "condition_factors": {
      "excellent": 0.10,
      "good": 0.05,
      "average": 0.0,
      "poor": -0.10,
      "very_poor": -0.20
    }
  }
}
```

### 7. Update Formulas
**POST** `/formulas`

Update formula configurations.

**Headers:**
- `Content-Type: application/json`
- `Authorization: Bearer {admin_token}` (required)

**Example Request:**
```json
{
  "land_coefficient": 0.12,
  "bedroom_bonus_multiplier": 6,
  "type_coefficients": {
    "apartment": -0.08,
    "house": 0.0,
    "villa": 0.15
  },
  "energy_efficiency_bonus": {
    "A": 0.12,
    "B": 0.08,
    "C": 0.0,
    "D": -0.08,
    "E": -0.15,
    "F": -0.20,
    "G": -0.25
  }
}
```

**Example Response:**
```json
{
  "status": "ok",
  "message": "Formulas saved successfully"
}
```

## Error Responses

### 400 Bad Request
```json
{
  "error": "Missing field: commune"
}
```

### 404 Not Found
```json
{
  "error": "Evaluation not found"
}
```

### 500 Internal Server Error
```json
{
  "error": "Failed to read Excel file: Invalid file format"
}
```

## Test Data Examples

### Basic Apartment
```json
{
  "type": "apartment",
  "commune": "Luxembourg-Ville",
  "living_m2": 45
}
```

### Family House
```json
{
  "type": "house",
  "commune": "Hesperange",
  "land_ares": 8,
  "living_m2": 180,
  "bedrooms": 4,
  "terrace_m2": 25,
  "private_garden_m2": 400,
  "cellar_m2": 30,
  "garage_box_units": 1,
  "apply_year_coef": "yes",
  "year_built": 1995,
  "energy_efficiency": "D",
  "location_type": "suburban",
  "condition": "average"
}
```

### Luxury Villa
```json
{
  "type": "villa",
  "commune": "Strassen",
  "land_ares": 12,
  "living_m2": 250,
  "bedrooms": 5,
  "balcony_m2": 15,
  "terrace_m2": 40,
  "private_garden_m2": 800,
  "cellar_m2": 60,
  "garage_box_units": 2,
  "parking_indoor_units": 1,
  "parking_outdoor_units": 2,
  "apply_year_coef": "yes",
  "year_built": 2020,
  "energy_efficiency": "A",
  "location_type": "residential",
  "condition": "excellent",
  "special_features": {
    "swimming_pool": 0.15,
    "home_cinema": 0.05,
    "wine_cellar": 0.03
  },
  "custom_adjustments": {
    "market_premium": 0.08,
    "architectural_design": 0.05
  }
}
```

## Available Communes

The system supports all Luxembourg communes including:
- Luxembourg-Ville (€9,800/m²)
- Bertrange (€9,200/m²)
- Strassen (€9,400/m²)
- Hesperange (€8,800/m²)
- Esch-sur-Alzette (€6,800/m²)
- And 90+ more communes...

## Energy Efficiency Grades

- **A**: +10% bonus
- **B**: +5% bonus
- **C**: No adjustment
- **D**: -5% penalty
- **E**: -10% penalty
- **F**: -15% penalty
- **G**: -20% penalty

## Location Types

- **city_center**: +15% bonus
- **residential**: +5% bonus
- **suburban**: No adjustment
- **rural**: -10% penalty

## Property Conditions

- **excellent**: +10% bonus
- **good**: +5% bonus
- **average**: No adjustment
- **poor**: -10% penalty
- **very_poor**: -20% penalty
