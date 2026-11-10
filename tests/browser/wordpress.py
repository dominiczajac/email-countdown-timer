"""Shared login for the disposable WordPress browser fixtures."""
from playwright.sync_api import expect


def log_in(page, base, username, password):
    page.goto(base + '/wp-login.php')
    # Core focuses/selects the login field on a delayed timer. Filling before
    # that timer runs can redirect password input to the username field, leaving
    # native required-field validation to cancel the POST without navigation.
    page.wait_for_function("document.activeElement === document.getElementById('user_login')")
    page.locator('#user_login').fill(username)
    page.locator('#user_pass').fill(password)
    with page.expect_response(lambda response: response.request.method == 'POST' and '/wp-login.php' in response.url):
        page.locator('#wp-submit').click()
    page.wait_for_url('**/wp-admin/**', wait_until='domcontentloaded')
    expect(page.locator('#wpadminbar')).to_be_visible()
