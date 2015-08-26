require './bootstrap.rb'

feature 'Member Group List' do
  before(:each) do
    cp_session
    @page = MemberGroups.new
    @page.load
    no_php_js_errors
  end

  it 'shows the Member Group List page' do
    @page.all_there?.should == true
    @page.list.all_there?.should == true
  end

  context 'when creating a member group' do
    before :each do
      @page.new_group.click

      @page.all_there?.should == true
      @page.edit.all_there?.should == true

      @page.edit.name.set 'Moderators'
      @page.edit.description.set 'Moderators description.'
      @page.edit.security_lock[0].click
      @page.edit.website_access.each(&:click)
      @page.edit.can_view_profiles[0].click
      @page.edit.can_send_email[0].click
      @page.edit.can_delete_self[0].click
      @page.edit.mbr_delete_notify_emails.set 'team@ellislab.com'
      @page.edit.include_members_in.each(&:click)
      @page.edit.can_post_comments[0].click
      @page.edit.exclude_from_moderation[0].click
      @page.edit.comment_actions.each(&:click)
      @page.edit.can_search[0].click
      @page.edit.search_flood_control.set '60'
      @page.edit.can_send_private_messages[0].click
      @page.edit.prv_msg_send_limit.set '50'
      @page.edit.prv_msg_storage_limit.set '100'
      @page.edit.can_attach_in_private_messages[0].click
      @page.edit.can_send_bulletins[0].click
      @page.edit.can_access_cp[0].click
      @page.edit.cp_homepage[1].click
      @page.edit.footer_helper_links.each(&:click)
      @page.edit.can_admin_channels[0].click
      @page.edit.category_actions.each(&:click)
      @page.edit.channel_entry_actions.each(&:click)
      @page.edit.member_actions.each(&:click)
      @page.edit.allowed_channels.each(&:click)
      @page.edit.can_admin_design[0].click
      @page.edit.can_admin_templates[0].click
      @page.edit.allowed_template_groups.each(&:click)
      @page.edit.can_admin_modules[0].click
      @page.edit.addons_access.each(&:click)
      @page.edit.access_tools.each(&:click)
      @page.edit.submit.click
    end

    it 'creates a group successfully' do
      @page.list.groups.last.find('li.edit a').click

      @page.list.all_there?.should == false
      @page.edit.all_there?.should == true

      @page.edit.name.value.should == 'Moderators'
      @page.edit.description.value.should == 'Moderators description.'
      @page.edit.security_lock[0].checked?.should == true
      @page.edit.website_access.each { |e| e.checked?.should == true }
      @page.edit.can_view_profiles[0].checked?.should == true
      @page.edit.can_send_email[0].checked?.should == true
      @page.edit.can_delete_self[0].checked?.should == true
      @page.edit.mbr_delete_notify_emails.value.should == 'team@ellislab.com'
      @page.edit.include_members_in.each { |e| e.checked?.should == true }
      @page.edit.can_post_comments[0].checked?.should == true
      @page.edit.exclude_from_moderation[0].checked?.should == true
      @page.edit.comment_actions.each { |e| e.checked?.should == true }
      @page.edit.can_search[0].checked?.should == true
      @page.edit.search_flood_control.value.should == '60'
      @page.edit.can_send_private_messages[0].checked?.should == true
      @page.edit.prv_msg_send_limit.value.should == '50'
      @page.edit.prv_msg_storage_limit.value.should == '100'
      @page.edit.can_attach_in_private_messages[0].checked?.should == true
      @page.edit.can_send_bulletins[0].checked?.should == true
      @page.edit.can_access_cp[0].checked?.should == true
      @page.edit.cp_homepage[1].checked?.should == true
      @page.edit.footer_helper_links.each { |e| e.checked?.should == true }
      @page.edit.can_admin_channels[0].checked?.should == true
      @page.edit.category_actions.each { |e| e.checked?.should == true }
      @page.edit.channel_entry_actions.each { |e| e.checked?.should == true }
      @page.edit.member_actions.each { |e| e.checked?.should == true }
      @page.edit.allowed_channels.each { |e| e.checked?.should == true }
      @page.edit.can_admin_design[0].checked?.should == true
      @page.edit.can_admin_templates[0].checked?.should == true
      @page.edit.allowed_template_groups.each { |e| e.checked?.should == true }
      @page.edit.can_admin_modules[0].checked?.should == true
      @page.edit.addons_access.each { |e| e.checked?.should == true }
      @page.edit.access_tools.each { |e| e.checked?.should == true }
    end
  end
end
