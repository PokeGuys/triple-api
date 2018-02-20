# Triple

# System

## Show system property [GET /system/property]


+ Response 200 (application/json)
    + Body

            {
                "income": [
                    {
                        "id": 0,
                        "description": "Below 10000"
                    },
                    {
                        "id": 1,
                        "description": "10000-14999"
                    }
                ],
                "age": [
                    {
                        "id": 0,
                        "description": "Under 12"
                    },
                    {
                        "id": 1,
                        "description": "13-17"
                    }
                ],
                "city": [
                    {
                        "id": 1,
                        "country": "Taiwan",
                        "name": "Taipei",
                        "photo": ""
                    }
                ]
            }

# User [/member]

## Account registration [POST /member/register]


+ Request (application/x-www-form-urlencoded)
    + Body

            username=foo&password=bar&password_confirmation=bar&first_name=foo&last_name=bar&gender=M&income=0&age=2&email=foo@bar.com

+ Response 201 (application/json)

## Authentication [POST /member/authentication]


+ Request (application/x-www-form-urlencoded)
    + Body

            username=foo&password=bar

+ Response 200 (application/json)
    + Body

            {
                "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWV9.TJVA95OrM7E2cBab30RMHrHDcEfxjoYZgeFONFh7HgQ",
                "user": {
                    "id": 27,
                    "status": 1,
                    "username": "testing0278",
                    "email": "tripletest@gmail.co22m",
                    "first_name": "Test",
                    "last_name": "ing",
                    "age": 1,
                    "gender": "M",
                    "income": 1,
                    "created_at": 1518771320,
                    "updated_at": 1519158413
                }
            }

## Forget Password [POST /member/password/forget]


+ Request (application/x-www-form-urlencoded)
    + Body

            username=foo&email=foo@bar.com

+ Response 201 (application/json)

## Reset Password [POST /member/password/reset]


+ Request (application/x-www-form-urlencoded)
    + Body

            username=foo&password=bar&password_confirmation=bar&email=foo@bar.com&token=123456

+ Response 201 (application/json)

# Attraction [/attraction]

## Get attraction list [GET /attraction]


+ Response 200 (application/json)
    + Body

            {
                "data": [
                    {
                        "id": 1,
                        "name": "Taipei 101 Observatory",
                        "phone": "+886 2 8101 8898",
                        "email": null,
                        "website": "http://www.taipei-101.com.tw/tw/observatory-info.aspx",
                        "address": "110, Taiwan, Taipei City, Xinyi District, Section 5, Xinyi Road, 7號89樓",
                        "tags": [
                            "point_of_interest",
                            "establishment"
                        ],
                        "latitude": "25.0336076",
                        "longitude": "121.5647587",
                        "rating": "4.30",
                        "comment_count": 0,
                        "photo_count": 0,
                        "created_at": 1518970328,
                        "updated_at": 1518970329
                    }
                ]
            }

## Get attraction details [GET /attraction/{id}]


+ Parameters
    + id: (integer, required) - The id of attraction to view.

+ Response 200 (application/json)
    + Body

            {
                "data": {
                    "id": 1,
                    "name": "Taipei 101 Observatory",
                    "phone": "+886 2 8101 8898",
                    "email": null,
                    "website": "http://www.taipei-101.com.tw/tw/observatory-info.aspx",
                    "address": "110, Taiwan, Taipei City, Xinyi District, Section 5, Xinyi Road, 7號89樓",
                    "tags": [
                        "point_of_interest",
                        "establishment"
                    ],
                    "latitude": "25.0336076",
                    "longitude": "121.5647587",
                    "rating": "4.30",
                    "comment_count": 0,
                    "photo_count": 0,
                    "created_at": 1518970328,
                    "updated_at": 1518970329,
                    "comments": {
                        "data": []
                    }
                }
            }

# Trip [/trip]

## Get trip list created by user [GET /trip]


+ Response 200 (application/json)
    + Body

            {
                "data": [
                    {
                        "id": 298,
                        "title": "Testing Trip",
                        "owner_id": 27,
                        "owner": "Test ing",
                        "visit_date": "2018-02-20",
                        "visit_length": 3,
                        "created_at": 1518771616,
                        "updated_at": 1518771616,
                        "collaborators": [],
                        "image": ""
                    }
                ]
            }

## Get filtered trip list created by user [GET /trip/search/{keyword}]


+ Parameters
    + keyword: (string, required) - The keyword of trip title to filter.

+ Response 200 (application/json)
    + Body

            {
                "data": [
                    {
                        "id": 298,
                        "title": "Testing Trip",
                        "owner_id": 27,
                        "owner": "Test ing",
                        "visit_date": "2018-02-20",
                        "visit_length": 3,
                        "created_at": 1518771616,
                        "updated_at": 1518771616,
                        "collaborators": [],
                        "image": ""
                    }
                ]
            }

## Get trip detail created by user [GET /trip/{id}]


+ Parameters
    + id: (integer, required) - The id of trip to filter.

+ Response 200 (application/json)
    + Body

            {
                "id": 298,
                "title": "Testing Trip",
                "owner_id": 27,
                "owner": "Test ing",
                "visit_date": "2018-02-20",
                "visit_length": 1,
                "created_at": 1518771616,
                "updated_at": 1518771616,
                "collaborators": [
                    {
                        "id": 166641,
                        "user": {
                            "id": "1",
                            "first_name": "test",
                            "last_name": "test"
                        },
                        "created_at": 0,
                        "updated_at": 0
                    }
                ],
                "itinerary": [
                    {
                        "id": 29402,
                        "visit_date": "2018-02-20",
                        "created_at": 1518771616,
                        "updated_at": 1518771616,
                        "nodes": [
                            {
                                "id": 1,
                                "attraction_id": 796,
                                "name": "晴空巷咖啡",
                                "image": "",
                                "type": [],
                                "tag": "BREAKFAST",
                                "time": "08:30",
                                "duration": 3600,
                                "distance": 0,
                                "travel_duration": 0,
                                "fare": [],
                                "mode": "",
                                "route": []
                            },
                            {
                                "id": 2,
                                "attraction_id": 148,
                                "name": "Café Showroom",
                                "image": "https://lh3.googleusercontent.com/p/AF1QipNr1xJMVekvqd1bqg8PjWF8t5DiFJU7-2C54os=s1600-w400",
                                "type": [
                                    "ART_AND_ARCHITECTURE_LOVER",
                                    "FOODIE"
                                ],
                                "tag": "art_gallery",
                                "time": "9:32",
                                "duration": 3600,
                                "distance": 225,
                                "travel_duration": 167,
                                "fare": [],
                                "mode": "walking",
                                "route": []
                            }
                        ]
                    }
                ]
            }

## Create a new trip [POST /trip]


+ Request (application/x-www-form-urlencoded)
    + Body

            title=foo&visit_date=2018-02-21&visit_length=3&city_id=1&auto_generate=1

+ Response 201 (application/json)

## Edit an existing trip [PUT /trip/{id}]


+ Parameters
    + id: (integer, required) - The id of trip to edit.

+ Request (application/x-www-form-urlencoded)
    + Body

            title=foo&visit_date=2018-02-21&visit_length=3

+ Response 204 (application/json)

## Delete an existing trip [DELETE /trip/{id}]


+ Parameters
    + id: (integer, required) - The id of trip to delete.

+ Response 204 (application/json)
