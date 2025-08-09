from flask import Blueprint, render_template
from models.application import Application

stats_bp = Blueprint("stats", __name__)

ORDER = ["Pending", "Interview", "Accepted", "Rejected", "No Answer"]

@stats_bp.route("/")
def stats():
    # Count per status
    counts = {k: 0 for k in ORDER}
    rows = Application.query.all()
    for r in rows:
        key = r.status if r.status in counts else "Pending"
        counts[key] += 1

    labels = ORDER
    data = [counts[k] for k in labels]
    total = sum(data)

    return render_template(
        "stats.html",
        labels=labels,
        data=data,
        total=total,
        title="Stats"
    )
