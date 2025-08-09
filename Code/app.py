import flet as ft
from datetime import datetime, timedelta, date

# ---------- THEME ----------
COLORS = {
    "bg": "#0b1220",
    "panel": "#0e1626",
    "panel2": "#10192b",
    "text": "#ffffff",
    "muted": "#95a3b3",
    "primary": "#5b9dff",
    "border": "#1c2740",
    "ok": "#22c55e",
    "warn": "#fbbf24",
    "bad": "#ef4444",
}

RADIUS = 12
GAP = 12

STATUS_KEYS = ["Pending", "Interview", "Accepted", "Rejected", "No Answer"]
STATUS_COLOR = {
    "Pending": COLORS["primary"],
    "Interview": COLORS["warn"],
    "Accepted": COLORS["ok"],
    "Rejected": COLORS["bad"],
    "No Answer": COLORS["muted"],
}

# ---------- SMALL UI PARTS ----------
def Card(content: ft.Control):
    return ft.Container(
        bgcolor="#0f172a",
        border=ft.border.all(1, COLORS["border"]),
        border_radius=RADIUS,
        padding=14,
        content=content,
    )

def StatusPill(status: str):
    color = STATUS_COLOR.get(status, COLORS["primary"])
    return ft.Container(
        padding=ft.padding.symmetric(10, 6),
        border=ft.border.all(1, color),
        border_radius=999,
        content=ft.Row(
            spacing=8,
            vertical_alignment=ft.CrossAxisAlignment.CENTER,
            controls=[
                ft.Icon(ft.Icons.CIRCLE, size=10, color=color),
                ft.Text(status, size=12, color=color, weight="bold"),
            ],
        ),
    )

def BigTile(title: str, number: str, hint: str, bg: str, on_click):
    return ft.Container(
        expand=True,
        bgcolor=bg,
        border_radius=RADIUS,
        border=ft.border.all(1, COLORS["border"]),
        padding=14,
        on_click=on_click,
        content=ft.Column(
            spacing=4,
            horizontal_alignment=ft.CrossAxisAlignment.CENTER,
            controls=[
                ft.Text(title, size=12, color=COLORS["muted"]),
                ft.Text(number, size=22, weight="bold", color=COLORS["text"]),
                ft.Text(hint, size=12, color=COLORS["muted"]),
            ],
        ),
    )

# ---------- DEMO DATA ----------
def demo_items():
    now = datetime.now()
    return [
        {"id": "1", "company": "Acme Corp", "position": "Embedded Intern", "status": "Pending",   "statusUpdatedAt": (now - timedelta(days=3)).strftime("%Y-%m-%d")},
        {"id": "2", "company": "Volt Systems", "position": "Hardware Test",  "status": "Interview","statusUpdatedAt": (now - timedelta(days=1)).strftime("%Y-%m-%d")},
        {"id": "3", "company": "Nimbus",       "position": "Junior Engineer","status": "Rejected", "statusUpdatedAt": (now - timedelta(days=8)).strftime("%Y-%m-%d")},
        {"id": "4", "company": "Helios Labs",  "position": "QA Technician",  "status": "Accepted", "statusUpdatedAt": (now - timedelta(days=2)).strftime("%Y-%m-%d")},
        {"id": "5", "company": "BlueWave",     "position": "HW Student",     "status": "Pending",  "statusUpdatedAt": (now - timedelta(days=18)).strftime("%Y-%m-%d")},
    ]

# ---------- PAGES ----------
def HomeView(page: ft.Page, items: list[dict]):
    # counts by status (auto “No Answer” if Pending >= 14 days)
    counts = {k: 0 for k in STATUS_KEYS}
    for it in items:
        days = (datetime.now() - datetime.strptime(it["statusUpdatedAt"], "%Y-%m-%d")).days
        key = "No Answer" if it["status"] == "Pending" and days >= 14 else it["status"]
        counts[key] += 1
    total = sum(counts.values())
    recent = sorted(items, key=lambda x: x["statusUpdatedAt"], reverse=True)[:5]

    # --- Hero ---
    hero = ft.Container(
        bgcolor=COLORS["panel"],
        border=ft.border.all(1, COLORS["border"]),
        border_radius=RADIUS,
        padding=14,
        content=ft.Row(
            alignment=ft.MainAxisAlignment.SPACE_BETWEEN,
            vertical_alignment=ft.CrossAxisAlignment.CENTER,
            controls=[
                ft.Column(
                    spacing=6,
                    controls=[
                        ft.Text("Interviewly", size=26, weight="bold", color=COLORS["text"]),
                        ft.Container(width=140, height=4, bgcolor=COLORS["primary"], border_radius=2),
                        ft.Text("Track your applications at a glance.", color=COLORS["muted"]),
                    ],
                ),
            ],
        ),
    )

    # --- Tiles ---
    tiles = ft.Row(
        spacing=GAP,
        controls=[
            BigTile("Applications", str(len(items)), "View & update", "#0b1220", on_click=lambda e: page.go("/applications")),
            BigTile("Stats", f'{counts["Accepted"]}/{total}', "Progress & charts", "#10192b", on_click=lambda e: page.go("/stats")),
        ],
    )

    # --- Status overview (fixed track width using ProgressBar) ---
    def status_row(label: str):
        value = counts[label]
        pct = (value / total) if total else 0.0
        left = ft.Container(
            width=160,
            content=ft.Row(
                spacing=10,
                vertical_alignment=ft.CrossAxisAlignment.CENTER,
                controls=[StatusPill(label), ft.Text(str(value), color=COLORS["text"], weight="bold")],
            ),
        )
        bar = ft.Container(
            expand=True,
            content=ft.ProgressBar(value=pct, color=STATUS_COLOR[label], bgcolor=COLORS["border"], height=10),
        )
        return ft.Row(spacing=12, vertical_alignment=ft.CrossAxisAlignment.CENTER, controls=[left, bar])

    status_overview = Card(
        ft.Column(
            spacing=10,
            controls=[
                ft.Row(
                    alignment=ft.MainAxisAlignment.SPACE_BETWEEN,
                    controls=[
                        ft.Text("Status overview", weight="bold", color=COLORS["text"]),
                        ft.TextButton("View charts →", on_click=lambda e: page.go("/stats")),
                    ],
                ),
                *(status_row(k) for k in ["Pending", "Interview", "Accepted", "Rejected", "No Answer"]),
            ],
        )
    )

    # --- Recent updates ---
    recent_updates = Card(
        ft.Column(
            spacing=10,
            controls=[
                ft.Text("Recent updates", weight="bold", color=COLORS["text"]),
                *(
                    [
                        ft.Container(
                            padding=ft.padding.symmetric(0, 10),
                            border=ft.border.only(bottom=ft.BorderSide(1, COLORS["border"])),
                            content=ft.Row(
                                alignment=ft.MainAxisAlignment.SPACE_BETWEEN,
                                vertical_alignment=ft.CrossAxisAlignment.CENTER,
                                controls=[
                                    ft.Column(
                                        spacing=2,
                                        controls=[
                                            ft.Text(r["position"], color=COLORS["text"], weight="bold"),
                                            ft.Text(r["company"], color=COLORS["muted"], size=12),
                                        ],
                                    ),
                                    StatusPill(r["status"]),
                                ],
                            ),
                        )
                        for r in recent
                    ]
                    if len(recent) > 0
                    else [ft.Text("Nothing yet. Add your first application.", color=COLORS["muted"])]
                ),
            ],
        )
    )

    # --- Quick actions ---
    actions = ft.Row(
        spacing=GAP,
        controls=[
            ft.ElevatedButton("My Applications", on_click=lambda e: page.go("/applications"), icon=ft.Icons.VIEW_LIST),
            ft.OutlinedButton("Add New", on_click=lambda e: page.go("/new"), icon=ft.Icons.ADD),
        ],
    )

    content = ft.ListView(
        expand=True,
        padding=GAP,
        spacing=GAP,
        controls=[hero, tiles, status_overview, recent_updates, actions],
    )
    return content


def ApplicationsView(page: ft.Page, items: list[dict]):
    lst = ft.ListView(spacing=8, expand=True)
    for it in sorted(items, key=lambda x: x["statusUpdatedAt"], reverse=True):
        lst.controls.append(
            Card(
                ft.Row(
                    alignment=ft.MainAxisAlignment.SPACE_BETWEEN,
                    vertical_alignment=ft.CrossAxisAlignment.CENTER,
                    controls=[
                        ft.Column(
                            spacing=2,
                            controls=[
                                ft.Text(it["position"], weight="bold", color=COLORS["text"]),
                                ft.Text(it["company"], color=COLORS["muted"]),
                            ],
                        ),
                        StatusPill(it["status"]),
                    ],
                )
            )
        )
    return ft.Column([ft.Text("Applications", size=20, weight="bold"), lst], spacing=GAP)


def StatsView(page: ft.Page):
    return ft.Column(
        spacing=8,
        controls=[
            ft.Text("Stats", size=20, weight="bold"),
            ft.Text("Charts coming soon…", color=COLORS["muted"]),
        ],
    )


def NewView(page: ft.Page, items: list[dict], refresh_home):
    company = ft.TextField(label="Company", filled=True)
    position = ft.TextField(label="Position", filled=True)
    status = ft.Dropdown(
        label="Status",
        options=[ft.dropdown.Option(s) for s in ["Pending", "Interview", "Accepted", "Rejected"]],
        value="Pending",
        filled=True,
    )

    def save(_):
        if not company.value.strip() or not position.value.strip():
            page.snack_bar = ft.SnackBar(ft.Text("Company and Position are required"), open=True)
            page.update()
            return
        items.insert(
            0,
            {
                "id": str(len(items) + 1),
                "company": company.value.strip(),
                "position": position.value.strip(),
                "status": status.value,
                "statusUpdatedAt": date.today().strftime("%Y-%m-%d"),
            },
        )
        page.snack_bar = ft.SnackBar(ft.Text("Saved"), open=True)
        page.go("/")  # back home
        refresh_home()

    return ft.Column(
        spacing=12,
        controls=[
            ft.Text("Add New", size=20, weight="bold"),
            company,
            position,
            status,
            ft.ElevatedButton("Save", icon=ft.Icons.SAVE, on_click=save),
        ],
    )

# ---------- APP ----------
def main(page: ft.Page):
    page.title = "Interviewly"
    page.theme_mode = "dark"
    page.bgcolor = COLORS["bg"]
    page.padding = 0

    items = demo_items()
    home_view = None  # will keep reference

    def route_change(e: ft.RouteChangeEvent):
        nonlocal home_view
        page.views.clear()

        appbar = ft.AppBar(
            leading=ft.Icon(ft.Icons.CASES_OUTLINED),
            title=ft.Text("Interviewly"),
            center_title=False,
            bgcolor=COLORS["bg"],
        )

        if page.route == "/":
            home_view = HomeView(page, items)
            page.views.append(ft.View("/", controls=[appbar, home_view]))
        elif page.route == "/applications":
            page.views.append(ft.View("/applications", controls=[appbar, ft.Container(padding=16, content=ApplicationsView(page, items))]))
        elif page.route == "/stats":
            page.views.append(ft.View("/stats", controls=[appbar, ft.Container(padding=16, content=StatsView(page))]))
        elif page.route == "/new":
            def refresh_home():
                if home_view:
                    page.go("/")  # triggers rebuild
            page.views.append(ft.View("/new", controls=[appbar, ft.Container(padding=16, content=NewView(page, items, refresh_home))]))
        else:
            page.views.append(ft.View("/404", controls=[appbar, ft.Text("Not found")]))

        page.update()

    def view_pop(e: ft.ViewPopEvent):
        page.views.pop()
        page.update()

    page.on_route_change = route_change
    page.on_view_pop = view_pop
    page.go("/")


ft.app(target=main)
