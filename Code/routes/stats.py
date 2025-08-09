from flask import Blueprint, render_template
from models.application import Application

stats_bp = Blueprint("stats", __name__)

@stats_bp.route("/")
def stats():
    total = Application.query.count()
    pending = Application.query.filter_by(status="Pending").count()
    interview = Application.query.filter_by(status="Interview").count()
    accepted = Application.query.filter_by(status="Accepted").count()
    rejected = Application.query.filter_by(status="Rejected").count()
    return render_template("stats.html", total=total, pending=pending, interview=interview, accepted=accepted, rejected=rejected, title="Stats")
