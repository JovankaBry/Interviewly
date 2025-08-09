from flask import Flask, render_template
from models.application import db
from routes.home import home_bp
from routes.applications import applications_bp
from routes.stats import stats_bp
import os

app = Flask(__name__)
app.config.from_pyfile("config.py")

# Init DB once, create tables if needed
db.init_app(app)
with app.app_context():
    os.makedirs("database", exist_ok=True)
    db.create_all()

# Blueprints
app.register_blueprint(home_bp)
app.register_blueprint(applications_bp, url_prefix="/applications")
app.register_blueprint(stats_bp, url_prefix="/stats")

@app.errorhandler(404)
def not_found(_):
    return render_template("404.html", title="Not Found"), 404

if __name__ == "__main__":
    app.run(debug=True)
