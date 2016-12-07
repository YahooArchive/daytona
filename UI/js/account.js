function buildAccountCard(user, email, isAdmin, state) {
    var container = $("#account-info-panel .panel-body");
    var accountCard = $("<div/>").addClass("account-card");
    var usernameRow = $("<div/>").addClass("account-row col-xs-12");
    var accountUsernameLabel = $("<label/>").addClass("account-label col-sm-1 col-xs-12")
        .text("User");
    var accountUsername = $("<label/>").addClass("account-label-value col-sm-11 col-xs-12")
        .text(user);
    $(usernameRow).append(accountUsernameLabel)
        .append(accountUsername);
    var emailRow = $("<div/>").addClass("account-row col-xs-12");
    var accountEmailLabel = $("<label/>").addClass("account-label col-sm-1 col-xs-12")
        .text("Email");
    var accountEmail = $("<label/>").addClass("account-label-value col-sm-11 col-xs-12")
        .text(email);
    $(emailRow).append(accountEmailLabel)
        .append(accountEmail);
    var roleRow = $("<div/>").addClass("account-row col-xs-12");
    var accountRoleLabel = $("<label/>").addClass("account-label col-sm-1 col-xs-12")
        .text("Role");
    var accountRole = $("<label/>").addClass("account-label-value col-sm-11 col-xs-12")
        .text(isAdmin ? "Admin" : "General User");
    $(roleRow).append(accountRoleLabel)
        .append(accountRole);

    var stateRow = $("<div/>").addClass("account-row col-xs-12");
    var accountStateLabel = $("<label/>").addClass("account-label col-sm-1 col-xs-12")
        .text("State");
    var accountState = $("<label/>").addClass("account-label-value col-sm-11 col-xs-12")
        .text(state);
    $(stateRow).append(accountStateLabel)
        .append(accountState);

    $(accountCard).append(usernameRow)
        .append(emailRow)
        .append(stateRow)
        .append(roleRow);

    var changeSettingsLink = $("<a/>").attr("href", "#changePasswordModal")
        .attr("data-toggle", "modal")
        .text(" Change Password")
        .prepend($("<i/>").addClass("fa fa-cog account-cog"));
    $("#account-info-panel .panel-heading").append(changeSettingsLink);

    $(container).append(accountCard);
}

function buildAdminPanel(json) {
    var accountsMap = JSON.parse(json);
    var container = $("#account-admin-panel .panel-body");
/*    var adminApprovalRow = $("<div/>")
    var adminApprovalLabel = $("<label/>").text("New Account Approval: ");
    var adminApprovalToggle = $("<i/>").addClass("fa fa-toggle-on");
    $(adminApprovalRow).append(adminApprovalLabel)
        .append(adminApprovalToggle);
    $(container).append(adminApprovalRow);*/
    var adminTable = $("<table/>").attr("id", "account-admin-table")
        .addClass("table table-hover table-striped table-condensed");
    $(adminTable).append($("<thead/>").append($("<tr/>")));
    $(adminTable).find("tr").append($("<th/>").css("width", "30%").text("Username").attr("data-defaultsort", "asc"))
        .append($("<th/>").css("width", "40%").text("Email"))
        .append($("<th/>").css("width", "10%").text("Admin"))
        .append($("<th/>").css("width", "20%").text("State"));
    var adminTableBody = $("<tbody/>").appendTo(adminTable);
    for(var i = 0; i < accountsMap.length; ++i) {
        var accountObj = accountsMap[i];
        var accountRow = $("<tr/>").click(function() {
            var role = $(this).find("td").eq(2).text() == "0" ? "General User" : "Admin";
            $("#account-setting-user").text($(this).find("td").eq(0).text());
            $("#account-setting-user-hidden").val($(this).find("td").eq(0).text());
            $("#account-setting-email").val($(this).find("td").eq(1).text());
            $("#account-setting-state").val($(this).find("td").eq(3).text());
            $("#account-setting-role").text(role);
            $("#accountSettingsModal").modal("show");
        });
        $(accountRow).append($("<td/>").text(accountObj.username))
            .append($("<td/>").text(accountObj.email))
            .append($("<td/>").text(accountObj.is_admin))
            .append($("<td/>").text(accountObj.user_state));
        $(adminTableBody).append(accountRow);
    }
    $(container).append(adminTable);
}
