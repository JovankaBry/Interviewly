# routes/stats.py
from flask import Blueprint, render_template
from models.application import Application

stats_bp = Blueprint("stats", __name__)
ORDER = ["Pending","Interview","Accepted","Rejected","No Answer"]

@stats_bp.route("/")
def stats():
    counts = {k: 0 for k in ORDER}
    for r in Application.query.all():
        counts[r.status] += 1
    labels = ORDER
    data = [counts[k] for k in labels]
    return render_template("stats.html", labels=labels, data=data, total=sum(data), title="Stats")