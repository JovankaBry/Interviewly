import os
BASE_DIR = os.path.abspath(os.path.dirname(__file__))
SQLALCHEMY_DATABASE_URI = f"sqlite:///{os.path.join(BASE_DIR, 'database', 'interviewly.db')}"
SQLALCHEMY_TRACK_MODIFICATIONS = False
SECRET_KEY = "change-me"