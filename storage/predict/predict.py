import sys
import json
import keras
import numpy as np
import pandas as pd
from sklearn.preprocessing import LabelEncoder, OneHotEncoder
from sklearn.preprocessing import StandardScaler
from keras.models import load_model

AVAILABLE_COLUMN = [
    'family_holiday_maker','foodie','backpacker','history_buff',
    'nightlife_seeker','eco_tourist','trendsetter','nature_lover',
    'urban_explorer','thrill_seeker','beach_goer','60+_traveller',
    'like_a_local','luxury_traveller','vegetarian','shopping_fanatic',
    'thrifty_traveller','art_and_architecture_lover','peace_and_quiet_seeker'
]

# read dataset
def readfile(filename):
    dataset = pd.read_csv(filename)

    return dataset

def hot_encode(X):
    return OneHotEncoder().fit(X)

def preprocess(dataset):
    X = dataset.iloc[:, 1:3].values
    y = dataset.iloc[:, 4:].values

    # encode age
    X[:, 0] = LabelEncoder().fit_transform(X[:, 0])
    # encode gender
    X[:, 1] = LabelEncoder().fit_transform(X[:, 1])
    # encode country
    # X[:, 2] = LabelEncoder().fit_transform(X[:, 2])

    # dummy variables (binary one-hot encoding)
    enc = hot_encode(X)
    X = enc.transform(X).toarray()

    return X,y,enc


def predict(age, gender):
    model = load_model('preference.model')
    dataset = readfile('users.csv')
    X, y, enc = preprocess(dataset)
    data = enc.transform(np.array([[age, gender]])).toarray()

    # preprocessing new observation
    # scale new observation
    sc_X = StandardScaler()
    X = sc_X.fit_transform(X)
    data = sc_X.transform(data)
    prediction_prob = model.predict(data)
    resp = []
    for idx, value in np.ndenumerate(prediction_prob[0]):
        tag = AVAILABLE_COLUMN[idx[0]]
        resp.append({'key': tag, 'value': value})
    print(resp)


if __name__ == '__main__':
    age = sys.argv[1]
    gender = sys.argv[2]
    predict(age, gender)