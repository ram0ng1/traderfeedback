// js/src/forum/modals/SelectUserModal.ts
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import username from 'flarum/common/helpers/username';
import avatar from 'flarum/common/helpers/avatar';
import KeyboardNavigatable from 'flarum/common/utils/KeyboardNavigatable';
import FeedbackModal from './FeedbackModal';
import app from 'flarum/forum/app';

export default class SelectUserModal extends Modal {
  oninit(vnode) {
    super.oninit(vnode);
    
    this.discussion = this.attrs.discussion;
    this.users = this.extractUsersFromDiscussion();
    this.filter = '';
    this.selectedIndex = 0;
    this.navigator = new KeyboardNavigatable();
    
    this.navigator
      .onUp(() => { 
        this.selectedIndex = Math.max(0, this.selectedIndex - 1);
        m.redraw();
      })
      .onDown(() => { 
        this.selectedIndex = Math.min(this.filteredUsers().length - 1, this.selectedIndex + 1);
        m.redraw();
      })
      .onSelect(() => this.selectUser(this.filteredUsers()[this.selectedIndex]));
  }

  extractUsersFromDiscussion() {
    const posts = this.discussion.posts();
    const uniqueUsers = new Map();
    const currentUserId = app.session.user?.id();

    posts?.forEach(post => {
      const user = post.user();
      if (user && user.id() !== currentUserId) {
        uniqueUsers.set(user.id(), user);
      }
    });

    return Array.from(uniqueUsers.values());
  }

  filteredUsers() {
    if (!this.filter) return this.users;
    const query = this.filter.toLowerCase();
    return this.users.filter(u => u.username().toLowerCase().includes(query));
  }

  selectUser(user) {
    // DÜZELTME: Önce modal'ı kapat, sonra yeni modal'ı aç
    this.hide();
    
    // Modal animasyonunun bitmesini bekle
    setTimeout(() => {
      app.modal.show(FeedbackModal, {
        user,
        discussionUrl: window.location.href,
        autoFillDiscussion: true
      });
    }, 200);
  }

  className() {
    return 'SelectUserModal Modal--small';
  }

  title() {
    return app.translator.trans('huseyinfiliz-traderfeedback.forum.discussion_actions.select_user_title');
  }

  content() {
    const users = this.filteredUsers();
    
    return m('.Modal-body', [
      m('.Form-group', [
        m('input.FormControl', {
          placeholder: app.translator.trans('huseyinfiliz-traderfeedback.forum.discussion_actions.search_user_placeholder') || 'Search users...',
          value: this.filter,
          oninput: e => { 
            this.filter = e.target.value; 
            this.selectedIndex = 0; 
            m.redraw();
          },
          onkeydown: this.navigator.navigate.bind(this.navigator),
          oncreate: vnode => vnode.dom.focus()
        })
      ]),
      m('.UserList', users.length ? 
        users.map((user, index) => 
          m('.UserListItem', {
            className: index === this.selectedIndex ? 'active' : '',
            onclick: () => this.selectUser(user)
          }, [
            avatar(user, {className: 'UserListItem-avatar'}),
            m('.UserListItem-info', username(user))
          ])
        ) : 
        m('.UserList-empty', app.translator.trans('huseyinfiliz-traderfeedback.forum.discussion_actions.no_users_found'))
      )
    ]);
  }
}